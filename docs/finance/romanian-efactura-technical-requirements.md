# RO e-Factura technical integration requirements

**Status:** technical design baseline; implementation approval pending
**Research date:** 25 October 2026
**Scope:** ANAF/SPV authorization, RO e-Factura API integration, XML/profile validation, submission/status/download flow, resilience, evidence retention, security and operational ownership.

This document extends [the Romanian invoicing requirements baseline](romanian-invoicing-requirements.md). It is a design contract, not a claim that the application currently integrates with ANAF. No API credentials, production access, or live submission has been configured or tested.

## 1. Current repository state

As of 25 October 2026, the repository has an additive canonical issued-invoice lifecycle, immutable snapshot hashes, private rendered HTML and UBL 2.1 XML artifacts, pinned local schema/profile validation, an OAuth token provider, and a test/production-separated ANAF upload/status/download client. Submission attempts, external message IDs, response artifacts, authority errors, correction links and synthetic end-to-end tests are persisted. See [structured generation](romanian-efactura-structured-generation.md), [submission operations](romanian-efactura-submission.md), and [evidence retention/access controls](romanian-invoice-evidence-retention.md).

This is implementation evidence, not proof of production readiness. No live ANAF credentials or live submissions have been verified, and the latest official profile artefacts, approved fiscal fixtures, retention schedule, storage/backup controls and qualified accounting/legal sign-off remain pending. The pinned local ruleset is not claimed as ANAF certification.

Existing legacy invoice rows must not be treated as complete electronic invoice documents. The implementation does not infer a legal invoice number, tax category, issue status, successful submission, acceptance, or payment from legacy fields or free-text status.

## 2. Official technical sources and precedence

Use the Ministry of Finance's current [RO e-Factura technical information page](https://mfinante.gov.ro/ro/web/efactura/informatii-tehnice) as the entry point for the current API specification, environments, schemas and validation rules. Re-check it immediately before implementation and deployment; pin the exact document/version and retrieval date in the implementation PR.

Supporting official sources inspected for this design:

- [ANAF OAuth application registration and token procedure (PDF)](https://static.anaf.ro/static/10/Anaf/Informatii_R/API/Oauth_procedura_inregistrare_aplicatii_portal_ANAF.pdf): describes app registration, qualified-certificate/SPV authorization, OAuth authorization-code flow, access/refresh tokens and token protection.
- [ANAF synchronous e-Factura service URL catalogue](https://static.anaf.ro/static/10/Anaf/Informatii_R/Servicii_web/url_eFactura.html): lists upload, message status, message list and download operations with separate test and production URLs. Treat these URLs as a discovery aid only; verify current endpoint paths and request contracts against the current Ministry specification.
- [ANAF invoice-completion tool: general fields](https://www.anaf.ro/CompletareFactura/faces/factura/informatiigenerale.xhtml), [seller](https://www.anaf.ro/CompletareFactura/faces/factura/vanzator.xhtml), [buyer](https://www.anaf.ro/CompletareFactura/faces/factura/cumparator.xhtml), and [invoice lines](https://www.anaf.ro/CompletareFactura/faces/factura/produse.xhtml): field-level examples for the XML-UBL format. The public UI currently identifies its generator as version 1.0.8; this alone does not prove that it is the current production validator/profile.
- [Order 1365/2021, official procedure](https://static.anaf.ro/static/10/Anaf/legislatie/OMF_1365_2021.pdf): states that submitted XML must follow EN 16931 and RO-CIUS and that the system assigns a unique upload/message reference.
- [Order 1366/2021, RO-CIUS specification](https://static.anaf.ro/static/10/Anaf/legislatie/OMF_1366_2021.pdf): legal/technical baseline to read with current consolidated amendments and the current technical package.
- [ANAF 2025 service catalogue](https://static.anaf.ro/static/10/Anaf/Informatii_R/Catalogul_serviciilor_ANAF_2025.pdf): explains that the original electronic invoice is the XML accompanied by the Ministry's electronic seal and describes web/API send and receive paths.

If an older ANAF guide or endpoint listing conflicts with the current Ministry technical package or current legislation, the current official source prevails. Do not copy legacy URLs, request fields, response codes, limits, or token lifetimes into production configuration without re-verification.

## 3. Integration boundary and lifecycle

The integration must be a controlled application service invoked by an authorized Management-OS workflow. UI components, AI agents, scheduled jobs, and MCP tools must not call ANAF directly or write invoice state directly. Laravel application/domain services own authorization, state transitions, persistence, audit events and retry decisions.

Keep these states distinct:

1. **Invoice domain state:** draft, approved/issued, corrected/cancelled, using exact transitions approved by Finance/accounting.
2. **Transmission state:** not_required, ready, queued, submitting, submitted/pending, response_available, retryable_failure, terminal_failure, or outcome_unknown. Final enum names are an implementation decision.
3. **ANAF processing result:** processing, accepted/validated, rejected, or any more specific values defined by the current official API.
4. **Payment state:** separate from invoice issuance and electronic transmission.

A successful HTTP request or upload response is not proof that the invoice passed asynchronous validation, was accepted, was delivered to the recipient, or was paid. Persist the ANAF upload/message identifier and poll/download the official result as documented. Interpret response fields using the pinned official API contract, not guessed status strings.

## 4. Authentication, authorization and secrets

The official ANAF OAuth procedure describes application registration and OAuth authorization using an authorized user and qualified digital certificate associated with the relevant SPV legal-person permissions. The registered application must be enabled for the RO e-Factura service. A token for the wrong user, certificate, or application permission must be treated as unauthorized, not worked around.

Implementation requirements:

- Register separate application/environment configuration for test and production as required by the current ANAF process.
- Use the current official OAuth authorization-code and refresh procedure. Confirm redirect URI, client authentication, parameters, token expiry, refresh rotation and revocation against the current document before coding.
- Store client secrets, access tokens, refresh tokens and certificate-related material only in approved secret storage / encrypted server-side configuration. Never commit them, expose them to the browser, include them in exception text, or write them to logs, analytics, audit payloads or CI artifacts.
- Restrict secret access to the minimum service identity and operators. Document owner, rotation/revocation procedure, expiry monitoring, and incident response for compromise.
- Redact `Authorization` headers, OAuth codes, token responses, client secrets, certificate identifiers where sensitive, XML bodies, personal identifiers and invoice data from logs.
- Treat expired/invalid tokens and revoked permissions as explicit authentication failures that require controlled refresh or operator action. Do not endlessly retry a permanent 401/403 response.
- Never use production credentials in local development, CI, fixture tests or test-environment exercises.

The current OAuth PDF contains token lifetimes and example parameters, but these are versioned external behavior. Verify them at implementation time rather than hard-coding lifetime assumptions in the requirements.

## 5. XML generation and validation contract

Structured XML generation depends on the approved canonical invoice model and approved tax policy; this issue does not implement either.

Before upload, the future implementation must:

1. Generate XML from an immutable, approved invoice snapshot, not directly from mutable UI fields or inferred legacy columns.
2. Use the current official UBL / EN 16931 and Romanian RO-CIUS specification/profile required by ANAF.
3. Validate XML well-formedness, schema, profile/business rules, required seller/buyer identifiers, invoice identity and dates, line quantity/unit/price/discount, tax category/rate/base/amount, currency and document totals.
4. Validate consistency between document totals and line/tax breakdowns using the approved decimal and rounding contract.
5. Return actionable field-level errors without transmitting invalid XML.
6. Record the exact specification/validator version and validation result.
7. Retain the exact bytes of the generated payload and a cryptographic SHA-256 hash. A re-rendered PDF is a derivative, not a replacement for the original structured XML.

Do not treat an ANAF transport acceptance as proof of legal tax treatment. Product/service classification, exemptions, reverse charge, B2B/B2C/B2G applicability, CNP cases, corrections and effective-dated VAT rules remain subject to the approved invoice requirements and qualified review.

## 6. Upload, polling, response download and reconciliation

Implement each external operation as a separately observable step based on the current official API contract:

- **Upload:** send one validated payload with the required company/taxpayer context and correlation data. Capture the returned upload/message identifier and response metadata.
- **Status:** query the documented status operation for that identifier. A pending status remains pending; do not mark it accepted based on upload success alone.
- **Response listing:** query the official response list using the supported time range/filters and reconcile each response to a known local submission. Handle delayed responses and duplicate listings.
- **Download:** retrieve the official response package and safely extract only expected entries. Validate archive contents and size limits; prevent path traversal, decompression bombs and unsafe file writes.
- **Result processing:** persist the raw official response package (or exact original bytes), response hash, timestamps, validation errors, and the Ministry's electronic seal/original XML when returned. Do not overwrite the submitted payload with a returned artifact.
- **Reconciliation:** provide a read-only diagnostic for submissions that are pending too long, have an unknown outcome, lack a downloaded response, or cannot be matched to a local invoice. Unmatched external responses must be quarantined for review, not attached by fuzzy invoice-number matching.

Confirm current API parameter names, accepted file types, size and date-range limits, status semantics, retention windows, throttling and response package formats from the current technical specification before implementation.

## 7. Timeouts, retries, idempotency and partial failure

An HTTP timeout can happen after ANAF has received a payload. It must not be interpreted as a definite failure.

- Create a durable local submission-attempt record before network I/O, linked to the immutable invoice version and payload hash.
- Store attempt ID, invoice ID/version, payload hash, environment, initiating actor/job, timestamps, request correlation, external message ID if received, response metadata, and redacted error category.
- Prevent concurrent duplicate submissions for the same invoice version and payload through a database-backed lock/uniqueness strategy, not an in-memory check alone.
- On timeout or connection loss after send, set outcome to **unknown** and reconcile through the official status/list/download operations before retrying.
- Retry only transient failures using bounded exponential backoff with jitter and an operational cap. Respect official rate limits and `Retry-After` where applicable.
- Do not automatically retry validation rejection, invalid input, missing permissions, revoked authorization, or other permanent errors. Preserve errors and require correction/authorized action.
- If a safe retry cannot be proven idempotent under the current API, require reconciliation/operator review rather than assuming a client request key is supported.
- Make response processing idempotent: the same external response downloaded twice must not duplicate invoice records, audit events, attachments, or state transitions.
- Record every attempt and result; never delete failed attempts or rewrite historical submission results.

## 8. Environments, configuration and deployment

Maintain explicit test and production environments. Base URLs and OAuth configuration must be named configuration values, not scattered literals. Fail closed if environment selection is missing or inconsistent. Production submission must require explicit deployment configuration and authorized operator capability.

Test coverage must use synthetic invoices and stubbed HTTP responses by default. No real taxpayer data or live credentials in automated CI. Use the official ANAF test environment only after confirming current enrollment, supported test cases and whether it can safely validate the intended operation. Production smoke tests must not submit real invoices unless separately approved with an operational plan.

Deployment documentation must include required configuration keys (names only, no secrets), queue/scheduler requirements if used, timeout/retry settings, log redaction, health signals, rollback behavior, token rotation, and operator steps for unresolved submissions.

## 9. Security, audit and privacy

- Enforce authorization server-side for invoice approval, issue, transmit, retry, download, correction and viewing sensitive payloads.
- Keep the submitter/service identity, reviewer/approver, actor, timestamps, reason and correlation IDs in the audit trail.
- Restrict raw XML, downloaded packages and taxpayer personal identifiers to least-privilege access; avoid including payloads in general activity logs.
- Apply bounded upload/download sizes, MIME/content validation, safe archive extraction, TLS verification and request timeouts.
- Keep secrets out of exception traces and structured logging. Test redaction explicitly.
- Preserve the original XML, official response/seal, payload hash and correction linkage according to the accountant/legal-approved retention schedule. Do not invent one retention duration for every artifact.
- Define incident steps for leaked tokens, wrong-entity submission, duplicate/unknown submissions, corrupt response packages and failed archival.

## 10. Proposed application components and persistence

These are logical responsibilities, not an approved exact class/table naming scheme:

- **ANAF OAuth client:** authorization callback/token exchange/refresh and expiry handling; secret storage boundary.
- **e-Factura API client:** typed upload, status, list and download operations; endpoint/version config; transport-level error mapping.
- **XML builder and validator:** deterministic serialization from the approved invoice snapshot plus pinned official schema/profile validation.
- **Submission coordinator:** authorization, state transition, attempt persistence, locking, retry policy, correlation and audit.
- **Response processor:** safe archive handling, response parsing, validation result mapping, original artifact persistence and idempotent reconciliation.
- **Operator diagnostics:** read-only view/command for pending, rejected, unknown, unmatched and overdue submissions, with runbook links.

A dedicated submission/attempt record should preserve at least: invoice reference and immutable version, environment, payload hash and artifact reference, specification version, applicability decision, local attempt ID, external message/upload ID, request/response timestamps, transport result, official processing result, redacted validation errors, response artifact/hash, actor/service identity and audit metadata. Define foreign keys and uniqueness only after the canonical invoice contract is approved.

Do not introduce the production schema or start transmission in this issue. The canonical model and invoice schema issues must establish exact fields, relations and migration safety first.

## 11. Required failure-mode tests for implementation issues

| Scenario | Required expected behavior |
| --- | --- |
| Valid synthetic XML | Passes the pinned schema/profile and approved business-rule fixtures. |
| Missing required field / invalid tax totals | Rejected locally with actionable errors; no upload attempt. |
| OAuth callback error, expired token, revoked permission | Safe error handling, no token leakage, no infinite retry. |
| API 429 / transient 5xx / network outage | Bounded retry policy, respects documented limits, preserves attempt history. |
| Timeout after upload may have succeeded | Mark outcome unknown; reconcile by external identifiers/status before resending. |
| Duplicate worker execution / simultaneous requests | One active attempt for the same invoice version/payload; no duplicate legal invoice. |
| Upload accepted but validation response delayed | Remain pending; poll/list/download with bounded schedule. |
| ANAF validation rejection | Preserve official errors and submitted payload; do not claim acceptance. |
| Duplicate response listing/download | Idempotent processing and one canonical result. |
| Malformed or oversized response archive | Reject/quarantine safely; no path traversal or unbounded extraction. |
| Unmatched official response | Quarantine and surface for manual reconciliation; do not fuzzy-link automatically. |
| Test/production configuration mismatch | Fail closed before network call. |
| Secret or payload-bearing exception | Redaction test confirms no secret or invoice content is logged. |
| Correction of an issued invoice | Link a new correction document to the original; never mutate original issued bytes. |

## 12. Open implementation gates

Before coding the API integration, the responsible owners must confirm:

1. The exact current API specification, API endpoints, schemas, request fields, response codes, rate limits and supported environment behavior.
2. The taxpayer/legal entity, authorized SPV account/certificate and ANAF OAuth app registration/ownership.
3. The approved canonical invoice contract, immutable issue snapshot, decimal/rounding policy, tax profiles and invoice lifecycle.
4. Official meaning of each submission/validation status and how long responses remain available.
5. Secret manager and deployment configuration, token renewal/revocation owner, and incident contact.
6. Queue/scheduler ownership, retry/backoff policy, alert thresholds and reconciliation runbook.
7. Access policy and retention schedule for XML, official seal, response package, rendered copy, audit events and personal data.
8. Acceptance fixtures and the named technical/Finance/accounting reviewers for the final implementation.

No production credentials or live invoice submission are authorized by this document.

## 13. Review record

- Requirements baseline: [Romanian invoicing requirements](romanian-invoicing-requirements.md); PR #415 merged.
- Current API specification/version pinned for implementation: **pending**.
- ANAF OAuth application / SPV access owner: **pending**.
- Test environment access and verified end-to-end response: **pending**.
- Technical owner and operational escalation: **pending**.
- Production implementation approval: **not granted**.
- Evidence retention schedule and qualified accounting/legal sign-off: **pending**; see [retention review gates](romanian-invoice-evidence-retention.md).
- Synthetic end-to-end rejection/correction/acceptance test: **implemented; does not substitute for an official ANAF test-environment run**.
