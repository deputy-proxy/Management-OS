# Romanian invoice evidence retention and access controls

**Status:** technical safeguards implemented; retention schedule and qualified accounting/legal approval remain pending.

## Scope and source of truth

The canonical issued invoice and its immutable snapshot hash remain the source of truth for commercial/fiscal facts. Generated HTML is a human-readable derivative. The UBL XML is the exact structured payload used for submission. ANAF upload/status responses and downloaded signed result archives are separate evidence artifacts. A credit note or other approved correction is a new issued record linked to the original; neither the original invoice nor its artifacts are overwritten to reflect a correction.

The app does not infer a retention duration. The existing requirements baseline cites Accounting Law 82/1991 and Order 2634/2015, and explicitly notes that different document categories can have different periods. Finance/accounting and legal owners must approve the exact current schedule per artifact category before disposal or automated expiration is enabled. This document is not a legal opinion or proof of compliance.

## Evidence categories and current disposition

| Artifact type | Retention category | Current disposition | Required reviewer decision |
| --- | --- | --- | --- |
| `invoice_html` | `rendered_invoice_derivative` | Private storage, hash verified, deletion blocked through the application model | Whether it is a retained accounting record or derivative and how long it must be retained |
| `ubl_xml` | `structured_invoice_payload` | Private storage, exact submitted bytes and hash retained, deletion blocked through the application model | Applicable statutory period and whether the submitted payload is the original for the relevant transaction |
| `efactura_response` | `authority_response` | Private storage, hash verified, access logged, deletion blocked through the application model | Retention period for upload/status responses and sensitive response detail |
| `efactura_result_archive` | `authority_signed_result` | Original ZIP bytes and hash retained, archive validation recorded, deletion blocked through the application model | Retention period for the authority result and Ministry electronic seal |
| Other artifact types | `unclassified_evidence` | Private storage, pending review, deletion blocked through the application model | Classification before retention policy can be approved |

Every artifact receives `retention_review_status=pending_review` unless an authorized reviewer records a policy reference. `retention_policy_reference`, reviewer identity and review timestamp are persisted per artifact. Recording an approved reference is an auditable application action, not an automated legal determination. Permission `customer_invoice.retention_review` must be assigned only to the designated Finance/accounting or legal reviewers. Do not use a generic administrator account as substitute sign-off.

There is intentionally no `retention_until` date or automatic deletion job yet. The exact schedule, legal holds, backup lifecycle, and disposal approval are unresolved. Until the schedule is approved, preserve evidence and do not purge it merely because it is old. A database administrator or storage operator could bypass application model guards; production IAM and backup policies must independently restrict direct deletion.

## Access and integrity

- Artifact bytes are stored on the `local` disk rooted at `storage/app/private`, not the public disk. Do not generate public URLs or expose storage keys as download links.
- `IssuedInvoiceArtifactAccessService` requires `customer_invoice.index`, restricts reads to the private `local` disk, verifies SHA-256 and byte size, and records `artifact_accessed` with artifact ID/type, hash and size only. It never puts document contents, tokens or ANAF error text in the access event.
- Missing or modified bytes fail closed. Restore the verified original from backup; do not silently regenerate over a historical artifact.
- Artifact identity and content metadata (invoice, type, storage key/disk, media type, byte size, hash, source link) are immutable through the Eloquent model. Application-level artifact deletion is prohibited. Retention metadata may only be changed through the dedicated service actions.
- Legal holds require a reason, are audited on placement/release, and prevent any future disposal workflow from treating the artifact as eligible. Hold reasons are stored with the artifact; event metadata contains only a hash of the reason.
- Retention review and legal-hold actions require dedicated server-side permissions. UI visibility alone is not authorization. Raw evidence must not be attached to generic activity logs, error messages, analytics, or CI artifacts.
- Source/response artifacts remain linked through submission and artifact relations. Corrections create new immutable documents; do not reassign an old payload or authority response to the corrected invoice.

## Lifecycle verification

The end-to-end feature fixture uses synthetic data and a fake HTTP transport. It covers:

1. Draft creation, approval, issuance, canonical snapshot hash and human-readable document generation.
2. UBL generation and exact payload hash comparison before upload.
3. Upload response correlation and idempotency.
4. Authority rejection, private response archive retention and safe error summaries.
5. Creation and issuance of a linked credit note without changing the original snapshot hash.
6. Submission of the corrected document, successful authority processing and result archive retention.
7. Hash/size verification when reading every retained artifact, and audit events for access, correction and authority outcomes.

Separate feature tests cover ambiguous upload timeouts (no blind retry), OAuth failure before upload, tampered payload rejection, invalid response archives, missing/invalid retention policy references, legal holds, blocked model deletion, and safe access-event metadata. These tests prove application behavior against synthetic fixtures only. They do not prove ANAF production acceptance, legal classification, backup recoverability, storage IAM, or specialist approval.

## Required approvals before production retention/disposal

Record a review reference and owner for each of the following before adding expiration or purge functionality:

- [ ] Romanian accountant/Finance owner approves the retention matrix per artifact type, including the starting event/date for each period.
- [ ] Romanian legal/privacy reviewer confirms statutory categories, exceptions, legal holds, personal-data minimization and any conflicting obligations.
- [ ] Technical owner verifies encrypted/private storage, production access logs, backup immutability, restore drills and disaster-recovery retention.
- [ ] A named reviewer confirms the approved policy reference and permission assignment for `customer_invoice.retention_review`.
- [ ] Disposal procedure requires independent authorization, legal-hold checks, auditable inventory, and verifiable deletion from primary storage and backups where legally permitted.

**Review record:** No qualified accountant/legal sign-off was supplied in this repository issue. The review remains pending; no claim of statutory compliance or approved retention schedule is made.
