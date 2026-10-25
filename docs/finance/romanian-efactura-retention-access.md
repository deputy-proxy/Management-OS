# Romanian e-Factura retention and access controls

**Status: implementation ready for review; retention period and compliance sign-off are not approved.**

This document separates technical controls implemented by the application from the retention schedule that must be approved by Romanian accounting/legal specialists. It does not claim statutory compliance or substitute for professional review.

## Protected record classes

| Record | Source of truth | Technical retention behavior | Access boundary |
| --- | --- | --- | --- |
| Issued invoice, lines, parties, tax breakdowns, numbering allocation, correction link and snapshot hash | Canonical relational record and immutable issuance snapshot | No update or purge path is provided by the invoice lifecycle service; corrections create linked records | Authenticated finance users with the relevant invoice permissions |
| Issuance, correction, submission, status and artifact-download events | Append-only issued_invoice_events rows | Events are not deleted by application workflows; payload data and secrets are excluded from safe metadata | Finance permissions; download events record actor, artifact ID/type, hash and size |
| Printable HTML and UBL XML | Private issued_invoice_artifacts row plus private bytes | SHA-256 and byte size are checked before reuse and before download; modified bytes are rejected | Authenticated customer_invoice.document.download permission |
| ANAF upload/status responses and signed result ZIP archives | Private artifact bytes plus artifact hash and linked submission attempt | Response bytes are retained as private artifacts; structured errors retain fingerprints and safe summaries, not raw authority messages | Same dedicated artifact-download permission; full error details stay in the private response/ZIP |
| Submission attempts and structured authority errors | Submission/attempt/error tables, linked by snapshot hash and correlation IDs | No automatic deletion; error records deduplicate by submission, response hash and message hash | Submission permission for mutations; artifact-download permission for source payloads |
| OAuth access/refresh tokens | Encrypted credential columns scoped by legal entity and environment | Laravel encrypted casts; no token value is written to audit metadata. Rotation/revocation must follow the approved credential-offboarding process | Deployment secret manager for client secrets; database access restricted to the application |

## Access and download rules

- The filesystem local disk is private. Artifact bytes are never served through a public storage URL.
- Downloads use an authenticated route and the dedicated customer_invoice.document.download permission.
- The download service verifies that the invoice is issued/corrected, reconstructs the immutable snapshot, verifies the artifact's private storage prefix, checks byte size and SHA-256, and refuses missing or tampered artifacts.
- Downloads are sent as attachments with Cache-Control: private, no-store, X-Content-Type-Options: nosniff, and a restrictive content security policy. The filename is generated from server-owned identifiers and hashes.
- Every successful download creates an invoice_artifact_downloaded event with the actor, artifact ID/type, SHA-256, byte size and media type. Raw XML, ZIP bytes, storage keys, OAuth tokens and authority messages are not copied into that event.
- No permission is inferred from knowledge of an artifact ID. The dedicated ability is checked before the artifact is looked up.
- Storage-integrity failures are not repaired by overwriting the artifact. Restore verified bytes from backup and retain the failed integrity evidence.

## Retention schedule: approval required

The application intentionally has no automated deletion job for canonical invoices, issued artifacts, submission receipts, error records or audit events. This prevents a generic software timer from deleting accounting evidence before a qualified retention decision exists. It is not a claim that indefinite retention is the correct legal period.

Before production sign-off, the designated Romanian accounting/legal reviewer must approve and record:

1. The statutory retention period for each accounting record class, including the start event and any applicable extensions.
2. The hold/suspension rules for audits, disputes, corrections, investigations and legal requests.
3. Backup retention and expiry, including how deleted records are removed from backups.
4. The credential retention/revocation period after a legal entity or integration is deactivated.
5. Who may authorize disposal, how approval is recorded, and what deletion evidence is retained.
6. Whether the signed ANAF result archive and all associated responses must be retained for the same period as the invoice record.

Until that approval is recorded, do not configure a scheduled purge or manually delete the artifacts to work around storage growth. If storage capacity becomes a concern, migrate the private artifact store using a controlled copy-and-hash verification process rather than deleting source evidence.

## Verification evidence and limitations

Automated tests cover private storage, permission-denied downloads, tampered/missing artifact rejection, download audit events, immutable snapshot hashes, submission status/error records and correction linkage. The end-to-end test suite uses synthetic invoices and a fake ANAF HTTP transport; it does not establish that ANAF accepted a live invoice.

**Still required before production:** live test-environment evidence using approved ANAF credentials and fixtures, and written sign-off from a qualified Romanian accounting/legal specialist for the retention schedule and fiscal workflow. No compliance claim should be made until those gates are completed.
