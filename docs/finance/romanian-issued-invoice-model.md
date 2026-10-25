# Proposed canonical issued-invoice data model

**Status:** design proposal for accountant/legal, Finance and engineering review; not approved for implementation
**Research date:** 25 October 2026
**Scope:** a canonical record for invoices issued by the company's legal entity/entities, the immutable invoice snapshot, lines and tax breakdowns, numbering, correction linkage, e-Factura lifecycle, validation and compatibility with existing records.

Related design inputs:
- [Romanian invoicing requirements baseline](romanian-invoicing-requirements.md)
- [RO e-Factura technical integration requirements](romanian-efactura-technical-requirements.md)
- [Existing invoice model audit and compatibility map](romanian-invoice-model-audit.md)
- [Finance/payroll capability matrix](../finance-payroll-readiness.md)

This document proposes a model contract for review. It does not add migrations, models, endpoints, numbering sequences, tax rules, or production invoice behavior. A qualified Romanian accountant/tax adviser, Finance owner and technical owner must approve the contract and record the decision before implementation.

## 1. Domain boundary

Do not turn `customer_invoices`, `vendor_invoices`, and a future issued-invoice record into one ambiguous “invoice” table.

| Concept | Meaning | Proposed ownership |
| --- | --- | --- |
| Customer billing register | Existing internal billing/customer-contract row. May contain draft, legacy or incompletely documented records. | Keep `customer_invoices` as a compatibility/source register during staged migration. It is not by itself proof that a legal invoice was issued. |
| Canonical issued invoice | An immutable legal-document snapshot issued by a named company legal entity to a buyer, with a legal number, lines, tax breakdowns and totals. | New `issued_invoices` aggregate after contract approval. |
| Vendor-received invoice | A supplier's document received by the company, with source XML/PDF and supplier identity. | Keep the separate `vendor_invoices` domain. Do not treat a supplier invoice as an invoice issued by the company. A later received-document contract may normalize it separately. |
| Payment / bank transaction | Money movement recorded from bank/account evidence. | Existing `transactions` aggregate. |
| Payment allocation | Reviewed allocation of a transaction to an invoice or fee. | Existing `payment_allocations`; remains separate from invoice issuance and external transmission. |
| e-Factura transmission | One or more attempts to transmit a particular immutable issued-invoice version to ANAF, with external identifiers and official response artifacts. | Separate `invoice_submissions` / submission-attempt records, linked to the issued invoice. |
| Correction / credit note | A new legal document that references a prior issued invoice and records the correction. | A new issued-invoice document with an explicit document type and original-invoice reference; never overwrite original issued bytes. |

An issued invoice may originate from a reviewed customer billing row, but the relationship is explicit and optional. One legacy customer invoice must not automatically become an issued invoice just because it has a customer, amount, or `Sent` status.

## 2. Proposed aggregate and entities

The table names below are illustrative names for review, not a migration instruction. Exact names, tenancy/legal-entity scoping and relationships are approval decisions.

### 2.1 `issued_invoices` header

| Field | Proposed type / constraint | Purpose and rule |
| --- | --- | --- |
| `id` | BIGINT primary key | Internal database key only; never the legal invoice number. |
| `public_id` | UUID, NOT NULL, unique | Stable opaque internal reference for URLs, API correlation and logs. Not a substitute for legal numbering. |
| `legal_entity_id` | FK, NOT NULL | Issuer legal entity whose tax/registration identity and numbering policy apply. Do not infer issuer from a generic app setting at issue time. |
| `legacy_customer_invoice_id` | Nullable FK to `customer_invoices.id`, unique when present | Optional provenance link for migration/reconciliation. A legacy row can exist without a canonical issued invoice. Preserve original ID and values. |
| `document_type` | Controlled code, NOT NULL | Candidate values: invoice, credit_note, debit_note, or other specifically approved document type. Legal/accounting owner approves the allowed set and meaning. |
| `series` | String, nullable only while draft | Approved numbering series. Required before issue if the approved policy uses a series. |
| `invoice_number` | String, nullable only while draft | Legal sequential number assigned by an approved procedure. Never derive from the database ID. |
| `issue_date` | DATE, nullable only while draft | Legal issue date; not the billing period or creation timestamp. |
| `supply_date` | DATE or nullable date/time as required by approved profile | Relevant supply/tax-point date; optionality depends on transaction and legal profile. |
| `due_date` | DATE, nullable | Contractual payment due date, distinct from legal issue and supply dates. |
| `currency_code` | CHAR(3), NOT NULL before issue | ISO 4217 currency code, with allowed codes and reporting-currency rules approved by Finance/accountant. No silent RON default for migrated rows. |
| `net_total` | DECIMAL(19,2), NOT NULL before issue | Document net total under the approved rounding policy. |
| `tax_total` | DECIMAL(19,2), NOT NULL before issue | Sum of tax breakdown amounts under the approved policy; may be zero for approved cases. |
| `gross_total` | DECIMAL(19,2), NOT NULL before issue | Approved payable/document total, reconciled to net + tax and approved adjustments. |
| `payment_terms_code` | Nullable controlled code or text snapshot | Terms agreed for this invoice; exact contract and allowed values require Finance approval. |
| `payment_status` | Prefer derived from allocations or separately controlled settlement projection | Must not be conflated with legal issue state or e-Factura processing result. If persisted for performance, define recomputation and audit rules. |
| `document_status` | Controlled state code | Proposed domain state machine in section 5. Exact state names and transitions require approval. |
| `corrects_issued_invoice_id` | Nullable self-FK | Original issued invoice when this document is a correction/credit note. Must be validated against approved correction scenarios. |
| `correction_reason_code`, `correction_reason` | Nullable controlled code / text | Required where approved correction procedure demands it; preserve the reason and source evidence. |
| `issued_snapshot_hash` | SHA-256 string, nullable before issue | Hash of the canonical immutable issued payload, with a versioned canonicalization rule. |
| `created_by`, `approved_by`, `issued_by` | Nullable actor/service references as lifecycle permits | Provenance for draft, approval and issue actions. Approval requirements and service identities must be defined. |
| `approved_at`, `issued_at` | Nullable timestamps | Distinct approval and issue instants. Never substitute `created_at`. |
| `created_at`, `updated_at`, `deleted_at` | Timestamps; deletion policy to be restricted after issue | Technical metadata. Issued documents should not be physically deleted; retention/legal hold rules must be explicit. |

**Numbering uniqueness proposal:** enforce a database uniqueness rule for the approved issuer scope plus series plus invoice number, accounting for any legal policy on series/fiscal year. Do not add a fiscal-year component or reset sequence annually unless the approved internal numbering procedure requires it. Allocation must be concurrency-safe, transactional, auditable and resistant to duplicate numbers under concurrent issue requests.

### 2.2 `issued_invoice_parties` snapshot

Store point-in-time party data for the issuer and buyer. The invoice must remain interpretable if the customer/vendor master record changes later.

| Field | Proposed type / constraint | Purpose |
| --- | --- | --- |
| `id`, `issued_invoice_id` | PK and FK; invoice FK restricted from destructive deletion | Link snapshot to its issued document. |
| `role` | Controlled code: seller / buyer (other roles only if approved) | One explicit party role per row; uniqueness on invoice + role unless the approved profile permits additional parties. |
| `legal_name` | VARCHAR, NOT NULL before issue | Name snapshot used on the document. |
| `country_code` | CHAR(2), NOT NULL before issue | Country snapshot. |
| `address_line_1`, `address_line_2`, `locality`, `postal_code`, `region` | Nullable/required by approved invoice profile | Structured address snapshot; mandatory fields vary by profile. |
| `tax_identifier` | Nullable string | Tax identifier in a typed namespace; don't overload one field with an undocumented CNP/CUI/VAT-ID interpretation. |
| `tax_identifier_type` | Nullable controlled code | Identifies the namespace and validation policy for the identifier. |
| `vat_registration_id` | Nullable string | VAT registration identifier where applicable, separate from national person/company identifiers. |
| `registration_id` | Nullable string | Commercial registration/legal entity identifier where applicable. |
| `email` | Nullable string snapshot | Optional contact detail if the approved profile and privacy policy allow it. |
| `created_at` | Timestamp | Technical metadata. |

Exact required party fields and special handling for consumers without an identifier, CNP-identified taxpayers, foreign parties, B2B/B2C/B2G and exempt/reverse-charge cases require qualified review. Do not copy the current `customers.cnp_cui` field directly into a single canonical tax-ID field without a reviewed transformation.

### 2.3 `issued_invoice_lines`

Each issued invoice has one or more immutable lines.

| Field | Proposed type / constraint | Purpose |
| --- | --- | --- |
| `id`, `issued_invoice_id` | PK and FK | Line identity and parent. |
| `line_number` | Positive integer, unique per invoice | Stable line order in document/XML. |
| `description` | Text, NOT NULL before issue | Product/service description snapshot. |
| `seller_item_code` | Nullable string | Internal item/service code, if used. |
| `quantity` | DECIMAL(19,6), positive for normal sale | Quantity snapshot; signs and zero quantity require explicit correction/document rules. |
| `unit_code` | Controlled code, NOT NULL before issue | Approved unit-of-measure code, aligned to the current XML profile. |
| `unit_price_net` | DECIMAL(19,6) candidate | Unit price before tax and before applicable discount. Precision and rounding require Finance approval. |
| `discount_amount` | DECIMAL(19,2), default zero only if approved | Explicit line discount amount. Percent discount, allowance/charge ordering and base need a defined contract. |
| `net_amount` | DECIMAL(19,2), NOT NULL before issue | Line net amount after discounts and approved line adjustments. |
| `currency_code` | Inherit invoice currency unless explicit multi-currency line support is approved | Avoid silent cross-currency lines. |
| `created_at` | Timestamp | Technical metadata. |

The suggested quantity/unit-price precision is a design candidate, not a settled accounting policy. The final schema must follow the approved UBL/EN 16931/RO-CIUS requirements and the application's approved decimal arithmetic and rounding rules.

### 2.4 `issued_invoice_line_taxes` and `issued_invoice_tax_breakdowns`

Line tax treatment and document tax totals must be explicit. Do not store only one aggregate `vat` amount.

**Line tax fields (proposed):**

- `id`, `issued_invoice_line_id`
- `tax_scheme_code` and `tax_category_code` (controlled values from the pinned profile)
- `tax_rate_percent` DECIMAL(7,4), nullable where the approved category has no rate
- `taxable_base` DECIMAL(19,2)
- `tax_amount` DECIMAL(19,2)
- `exemption_reason_code`, `exemption_reason` when required
- `reverse_charge` / special-scheme indicators only as supported by the approved contract and profile
- `created_at`

**Document tax breakdown fields (proposed):**

- `id`, `issued_invoice_id`
- `tax_scheme_code`, `tax_category_code`, `tax_rate_percent`
- `taxable_base` DECIMAL(19,2), `tax_amount` DECIMAL(19,2)
- `exemption_reason_code` / `exemption_reason` where applicable
- `created_at`

The breakdown must reconcile with line-level tax and header totals using a versioned calculation policy. A VAT percentage is not interchangeable with a monetary VAT amount. The approved rate/category catalog must be effective-dated and must not assume every supply is taxed at the standard rate.

### 2.5 `issued_invoice_artifacts`

Store immutable document evidence separately from the invoice's editable draft form.

Proposed fields: `id`, `issued_invoice_id`, `artifact_type`, `storage_disk`, `storage_key`, `media_type`, `byte_size`, `sha256`, `created_at`, `created_by`, and optional `source_artifact_id`.

Candidate artifact types include canonical issued payload, original XML submitted to ANAF, official response package/seal, rendered PDF copy, and supporting evidence. The type set, retention periods, legal hold, download authorization and encryption requirements must be approved. Do not put secret tokens or raw XML bodies into ordinary activity logs.

### 2.6 `invoice_submissions` and submission attempts

Use the technical contract in [RO e-Factura technical requirements](romanian-efactura-technical-requirements.md). Submission state is not the invoice's legal state.

A submission record should include: `id`, `issued_invoice_id`, immutable invoice version/hash, environment, technical-spec version, applicability decision/reason, payload artifact reference/hash, local correlation UUID, external ANAF upload/message ID, transmission state, official processing state, attempted/submitted/responded timestamps, redacted error code/message, response artifact/hash and actor/service identity. If multiple retries are required, store each attempt as an append-only child record rather than overwriting the previous attempt.

Do not create or send submissions until the issuer/entity, invoice model, ANAF API version, credentials owner and response mapping are approved.

## 3. Money, currency and calculation contract

- Use decimal columns for monetary values, not binary floating-point and not ambiguous integer amounts.
- Candidate header/line/tax monetary precision is `DECIMAL(19,2)`; candidate quantity/unit-price precision is `DECIMAL(19,6)`; candidate tax-rate precision is `DECIMAL(7,4)`. Finance/accounting must approve precision, maximum values, rounding mode, line-vs-document rounding and currency-specific exceptions before migration.
- Store currency explicitly using an approved ISO 4217 code catalog. Do not assume every legacy record is RON, and do not compare/sum different currencies without an approved FX/reporting-currency rule.
- Compute line extensions, discounts, taxable bases, tax amounts and totals in a deterministic domain service using decimal arithmetic. Validate the approved equations and profile-specific rounding.
- A document must be internally reconciled before approval/issue. Totals are not a free-text status or a value inferred from payment allocations.
- Preserve the calculation-policy version, input snapshot and validation result used at issue so a later policy change does not rewrite historical meaning.
- VAT eligibility, category, rate, exemptions, reverse charge and effective dates must come from approved tax rules. Synthetic arithmetic examples are not evidence that a real supply qualifies for a rate.

## 4. Validation contract

Validation should return stable field-level error codes plus safe human-readable messages. It must not silently repair legal invoice fields or mutate an issued document.

| Validation area | Proposed invariant | Error handling |
| --- | --- | --- |
| Issuer / buyer | Required snapshot fields and identifier rules match the approved profile. | Reject approval/issue with field errors; keep draft unchanged. |
| Legal numbering | Approved series/number allocated once within issuer scope; no duplicate legal number. | Return conflict/sequence error; no second issued document. |
| Dates | Issue/supply/due dates follow approved legal/business rules and chronology. | Field-level validation error; no implicit date substitution. |
| Lines | At least one line; line number unique and ordered; quantity/unit/description/price valid. | Reject invalid draft approval/issue. |
| Taxes | Each line has an approved tax category and applicable rate/mention; bases and amounts reconcile. | Reject unknown category/rate or inconsistent tax totals; never default to standard VAT. |
| Currency and totals | Currency allowed; net/tax/gross and document tax breakdown reconcile under a named policy version. | Return amount/currency errors with exact field references; no float coercion. |
| Corrections | Correction document type, original reference, reason and correction-specific constraints are valid. | Preserve original; reject correction without approved linkage/evidence. |
| XML profile | Generated XML passes pinned schema/profile and business-rule validation. | Keep draft/approved snapshot in a non-issued state; retain validation report; do not submit. |
| Authorization | Actor/service has permission for create, approve, issue, transmit, retry, download or correction. | Deny server-side; audit the decision without logging sensitive payloads. |
| Legacy compatibility | Any mapping from old rows is backed by an approved mapping and reconciliation evidence. | Quarantine ambiguous row; preserve old values and links. |

Error codes should be stable API/domain codes (for example `invoice.party.tax_identifier.required`, `invoice.number.duplicate`, `invoice.tax.category.unapproved`, `invoice.total.mismatch`, `invoice.xml.profile.invalid`, `invoice.submission.outcome_unknown`). Final code naming should match repository error conventions and the official API error mapping.

## 5. Lifecycle and transition proposal

Keep document state, e-Factura transmission state and payment state separate.

### Document lifecycle

| From | To | Guard / side effect |
| --- | --- | --- |
| Draft | Approved | Required fields and policy validation pass; approval permission and required Finance approval recorded. No legal number or external submission unless the approved procedure assigns it here. |
| Approved | Issued | Concurrency-safe legal number allocated according to approved policy; immutable header/party/line/tax snapshot persisted; issued payload hash and actor/timestamp recorded atomically. |
| Issued | Corrected / superseded (projection only) | Only after a new correction document is legally issued and linked. Original payload and history remain immutable; do not rewrite original to a new set of totals. |
| Draft / Approved | Cancelled | Only under an approved internal cancellation procedure; if a legal invoice was issued, do not use this transition to erase it. Use the legally approved correction/void process. |

Once a document is issued, direct edits to its legal fields, lines, taxes, number, or original payload are prohibited. A correction is a new document with its own number and history. Whether a pre-issue draft consumes a number, how void numbers are recorded, and the exact cancellation rules require accountant/Finance approval.

### e-Factura transmission lifecycle

Candidate states: `not_required`, `ready`, `queued`, `submitting`, `submitted_pending`, `response_available`, `accepted`, `rejected`, `retryable_failure`, `outcome_unknown`, `terminal_failure`.

- `not_required` requires a recorded applicability decision and reason, not a default assumption.
- `submitted_pending` requires an external message/upload identifier when returned by ANAF; a successful HTTP upload alone is not acceptance.
- `accepted` / `rejected` may be set only from the official response interpreted according to the pinned API spec.
- `outcome_unknown` requires reconciliation before any resend that could duplicate an external submission.
- Retries create append-only attempt records; they do not erase a prior response or turn a rejection into acceptance.
- Legal correction creates a new invoice document and a separate transmission lifecycle where required.

### Payment lifecycle

Payment status should be derived from approved confirmed allocations and invoice total, or stored as a projection with an auditable recomputation rule. Proposed display values are unpaid, partially_paid, paid and overpaid/unallocated only if Finance approves them. A transmission result does not imply payment; an allocation proposal does not imply settlement. Reversals and refunds must preserve their own audit history.

## 6. Compatibility mapping to current repository

| Existing field/relationship | Proposed handling |
| --- | --- |
| `customer_invoices.id` | Preserve as legacy PK; do not reuse as legal invoice number. |
| `customer_invoices.customer_id`, `customer_contract_id` | Retain provenance. Populate party snapshots only through an approved mapping and source evidence; master records are not historical snapshots. |
| `customer_invoices.amount_without_vat`, `vat` | Keep unchanged. Convert only rows whose units/semantics are independently verified. Do not treat integer values as major or minor units by assumption. |
| `customer_invoices.amount_without_vat_decimal`, `vat_decimal`, `currency_code`, `status_code` | Existing nullable canonical financial fields remain a separate normalization layer. They are not enough to form a legal issued invoice; preserve nulls for ambiguous rows. |
| `customer_invoices.month`, `status`, `invoice`, `identifier` | Preserve exact source values and current consumers. Do not map month to issue date, status to issued/accepted/paid, or identifier to legal number without an approved rule. |
| `vendor_invoices.*` | Keep in the received-supplier domain; do not link it as an issued invoice. Vendor supplier number and document artifacts need a separate received-invoice contract. |
| `transactions.customer_invoice_id` / `vendor_invoice_id` | Preserve existing references. Any future issued-invoice link is explicit; reconcile legacy rows with both links set before constraints or data conversion. |
| `payment_allocations.customer_invoice_id` / `vendor_invoice_id` | Preserve allocation history and targets. Decide whether a future allocation can target canonical issued invoices directly or continues through the customer billing register; do not silently retarget existing rows. |
| `product_revenues.customer_invoice_id` | Preserve as product revenue provenance. Do not assume product revenue is the legal invoice total or line item. |
| Dashboard, finance overview and calendar | Continue to read legacy tables during migration. If they later consume canonical issued invoices, update them in a separate reviewed change with regression tests and explicit date/status semantics. |
| Activity log, normalization audit, soft deletes | Preserve historical records. New invoice snapshots, submissions and corrections require stronger immutability and retention controls than editable legacy CRUD. |

## 7. Migration and rollout plan

1. Obtain written approval of this contract and the unresolved field/state decisions below.
2. Audit a read-only production snapshot: counts, nulls, distinct statuses/currencies, amount ranges, duplicates, orphans, dual invoice links, and source-artifact availability. Do not export personal/taxpayer data to unapproved locations.
3. Create canonical structures additively with restrictive foreign-key/deletion rules appropriate to issued records. Keep legacy fields and IDs intact.
4. Add a reviewed provenance link from eligible legacy customer billing rows to a canonical issued invoice. Do not create canonical records automatically from every old row.
5. Backfill only rows with verified source evidence and approved mappings. Use dry-run, idempotent batches, per-row decision reason, reconciliation report and quarantine for ambiguous cases.
6. Add a domain service for validation, approval and issuance with transactional number allocation, immutable snapshot creation, permissions and activity/audit events.
7. Add deterministic decimal tests against approved independent fixtures, including invalid and boundary cases, duplicate/concurrent numbering, corrections, legacy ambiguous rows and multi-currency rejection.
8. Add e-Factura submission only after the technical API spec/version and authorized test environment are verified; use synthetic fixtures and stubbed responses in CI.
9. Compare legacy and canonical reads/reports in shadow mode; reconcile counts, totals by currency, relationships, deleted rows and artifact hashes.
10. Cut over with a documented rollback/recovery plan and operator runbook. Do not drop legacy columns or stop existing consumers until the owner signs reconciliation and verifies the production MySQL version.
11. Retire legacy fields in a separate issue only after no consumer remains and retention requirements are met.

## 8. Decisions required for approval

The proposal is not ready to implement until the owners explicitly decide:

1. Which legal entities can issue invoices, their identifiers, tax status, authorized issuer and numbering scopes.
2. Whether the header party snapshots use structured columns as proposed or a versioned snapshot document plus indexed fields; define required seller/buyer fields by transaction profile.
3. Final field names, database engine support, money/quantity/rate precision, maximum values, rounding mode, line-vs-document rounding and permitted currencies.
4. Legal numbering procedure: series format, uniqueness scope, when a number is reserved, void-number handling, concurrency and recovery.
5. Approved invoice document types and correction/credit-note rules, including original-reference and reason requirements.
6. Exact document states, approval roles, who may issue, when the document becomes immutable, and allowed cancellation/void operations.
7. Tax categories/rates, effective dates, exemption/reverse-charge logic, FX policy and VAT-reporting currency.
8. B2B/B2C/B2G applicability, consumer identification/CNP cases and exemptions for the company's actual transactions.
9. Whether payment status is derived or persisted as a projection; allocation, refund, overpayment and write-off rules.
10. Artifact types, storage, hashes, access policy, legal hold and retention period per artifact.
11. Canonical e-Factura lifecycle/status mapping and ANAF API version/environment.
12. How eligible `customer_invoices` rows map to issued invoices, how vendor-received invoices stay separate, and how transaction/payment-allocation/product-revenue links are preserved.
13. Named accountant/tax adviser, Finance owner and technical owner approval, including date/version and any approved exceptions.

## 9. Contract example (illustrative only)

The shape below is a domain example, not an ANAF API request and not an approved schema. Values are synthetic. The tax rate is an arithmetic placeholder only; it must not be interpreted as tax eligibility.

```json
{
  "document_type": "invoice",
  "document_status": "draft",
  "legal_entity_id": "issuer-synthetic-01",
  "public_id": "8f21d3c0-6c3f-4b1b-9c18-5e6cdbf4a123",
  "numbering": {
    "series": null,
    "invoice_number": null
  },
  "dates": {
    "issue_date": null,
    "supply_date": null,
    "due_date": null
  },
  "currency_code": "RON",
  "parties": {
    "seller": {
      "legal_name": "Synthetic Seller SRL",
      "country_code": "RO",
      "tax_identifier_type": "CUI",
      "tax_identifier": "SYNTHETIC-ONLY"
    },
    "buyer": {
      "legal_name": "Synthetic Buyer SRL",
      "country_code": "RO",
      "tax_identifier_type": "CUI",
      "tax_identifier": "SYNTHETIC-ONLY"
    }
  },
  "lines": [
    {
      "line_number": 1,
      "description": "Synthetic service",
      "quantity": "1.000000",
      "unit_code": "C62",
      "unit_price_net": "100.000000",
      "discount_amount": "0.00",
      "net_amount": "100.00",
      "taxes": [
        {
          "tax_scheme_code": "VAT",
          "tax_category_code": "UNAPPROVED-EXAMPLE",
          "tax_rate_percent": "21.0000",
          "taxable_base": "100.00",
          "tax_amount": "21.00"
        }
      ]
    }
  ],
  "totals": {
    "net_total": "100.00",
    "tax_total": "21.00",
    "gross_total": "121.00"
  },
  "validation": {
    "policy_version": null,
    "result": "not_validated",
    "errors": []
  },
  "submission": {
    "state": "not_started",
    "external_message_id": null
  }
}
```

This example deliberately has no legal number, issue date, approved tax category, policy version or external message ID. It is a draft illustration only and must fail the production issue/submission gates until approved fields and rules are supplied.

## 10. Review and approval record

- Requirements baseline (issue 3.1): merged; project owner reported that approval was received for that baseline.
- Legacy mapping (issue 3.3): documented from source code; production data not audited.
- Proposed canonical model contract: **pending accountant/legal, Finance and technical approval**.
- Exact ANAF specification version / test environment: **pending**.
- Production schema migration: **not authorized by this document**.
- Production invoice issuance or automated tax decisions: **not authorized by this document**.
