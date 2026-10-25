# Issued invoice document generation

## Scope

`App\Services\Finance\IssuedInvoiceDocumentService` creates a deterministic, Romanian-language, printable HTML document for an already issued canonical invoice. It is separate from invoice calculation, numbering/issuance, and e-Factura transmission.

The document includes the issued number and dates, seller and buyer snapshots, line descriptions, quantities and units, unit prices, discounts, net line amounts, line tax details, document tax breakdowns, net/tax/gross totals, correction references, canonical public reference, and issued snapshot SHA-256.

## Integrity and storage

- Only `issued` or `corrected` invoices can be rendered. Draft, approved, cancelled, and incomplete records are rejected.
- Before rendering, the service reconstructs the canonical snapshot using policy versions in the issuance and draft events. It hashes that canonical JSON with the same encoding contract as issuance and refuses to render if it differs from `issued_snapshot_hash`.
- Dynamic invoice values are HTML-escaped. Monetary display uses Romanian separators and performs no accounting calculations.
- Generated HTML is stored on Laravel's private `local` disk under a key containing the invoice public ID, issued snapshot hash, and document hash. No public storage URL is created.
- The artifact row records media type, byte length, SHA-256, storage location, and creating actor. A valid existing artifact is reused. Missing or corrupt artifacts are not silently overwritten.
- Document content is deterministic and contains no generation timestamp. The document hash covers the exact stored bytes.

## Output limits and production gates

This implementation creates **printable HTML**, not a PDF. A PDF renderer is not currently installed or approved in the repository. The HTML can be opened through a separately authorized application workflow and printed or saved to PDF by a user agent. Production delivery requires an authenticated download/view endpoint and a reviewed PDF renderer/template if PDF output is required.

This document is a human-readable derivative, not the structured UBL/EN 16931/RO-CIUS e-Factura payload. It does not validate fiscal applicability, transmit an invoice, or imply ANAF/SPV acceptance. Official mandatory fields, tax treatment, and template approval remain subject to specialist review. Do not seed a production issuer or numbering series solely to enable document rendering.

## Validation and operator behavior

Feature tests use synthetic invoice data and fake private storage. They cover stable artifact reuse, content hash and size, Romanian monetary display, escaping of invoice-supplied text, rejection of draft documents, rejection of relational data that no longer matches the issued hash, and rejection of corrupt existing artifacts.

If generation fails, inspect the field-level validation error and issuance event metadata. Do not repair an issued invoice by editing its snapshot. Correct the source through the approved correction-document lifecycle, then generate the document for the new issued record.
