# Romanian invoice numbering and issuance controls

**Scope:** controlled draft, approval, issuance, numbering, cancellation-before-issue, correction linkage, and immutable issued snapshots. This workflow does not generate XML, submit to ANAF, or establish legal/tax compliance by itself.

## Lifecycle

`draft → approved → issued`

Drafts and approved-but-unissued documents can be cancelled with a required reason. Issued documents cannot be edited or deleted. A credit note or debit note is a new document that references an issued original and records a reason. When the correction is issued, the original is marked `corrected` without changing its issued snapshot hash; the new document and both lifecycle events remain linked.

Every successful state transition and issuance is written to `issued_invoice_events` in the same database transaction. The service also writes a safe activity-log event. Event metadata contains identifiers, policy versions, amounts/currency where useful, and hashes, not raw invoice payloads or credentials.

## Numbering and concurrency

Issuance requires an explicit caller-supplied numbering policy with a non-empty version, series, prefix, padding, and positive starting number. There are no seeded production series or implicit defaults. The first issuance for an issuer/series creates a sequence row. Issuance locks the legal-entity row and sequence row in a database transaction, assigns the next number, persists the immutable snapshot hash, advances the sequence, and records the event atomically. The database unique constraint on issuer/series/number remains the final duplicate guard.

A sequence is bound to the policy version used to create it. A policy-version change is rejected instead of silently resetting or altering the sequence. A reviewed migration procedure is required to change an existing numbering policy. The lock-and-sequence approach is intended for the repository's MySQL 8 deployment; production concurrency behavior must still be verified in staging on the exact deployed MySQL version.

## Required gates

- The issuer must exist and be active; this service does not create or guess legal entities.
- Draft creation requires an explicit versioned calculation policy accepted by `IssuedInvoiceCalculationService`, including allowed currencies, rounding mode, buyer-identifier requirement, and tax category/rate rules.
- Approval requires seller and buyer snapshots, at least one line, tax breakdowns, currency, and reconciled net/tax/gross totals.
- Issuance requires approval, an issue date, a currency, an active issuer, and an explicit numbering policy.
- Corrections require an issued original from the same legal entity, a credit/debit-note document type, and a reason. The original remains immutable except for its lifecycle status changing to `corrected` after the new correction is issued.
- Mutating service methods enforce existing customer-invoice create/update permissions server-side. Deployment must configure these permissions for the intended Finance/issuer roles.

## Boundaries and unresolved decisions

This change does not choose real series/prefixes, reset intervals, void-number rules, approval segregation, taxpayer-specific policy, or production tax categories. Those require documented approval from the legal-entity owner and qualified Romanian accounting/tax specialists. No production issuer is seeded, no route or UI is enabled, no invoice is issued automatically, and no ANAF call is made. Existing customer/vendor invoice rows are not converted or modified by this workflow.

## Validation

`tests/Feature/IssuedInvoiceLifecycleServiceTest.php` covers draft-to-issue, sequence advancement, invalid transition rejection without number consumption, policy-version mismatch, immutable issued data, and correction linkage while retaining the original snapshot hash. Schema uniqueness and MySQL-specific constraints are covered by `CanonicalIssuedInvoiceSchemaTest` and the MySQL CI job.
