# Romanian e-Factura structured XML generation

## Scope and separation

`IssuedInvoiceUblGenerationService` produces a deterministic UBL 2.1 XML derivative for an already issued canonical invoice. It does not issue invoices, calculate tax, send requests to ANAF/SPV, poll submission status, or record acceptance. Submission and credential handling remain a separate integration boundary.

Supported document types are standard invoices (`Invoice`, type code `380`) and credit notes (`CreditNote`, type code `381`) that reference the original issued invoice. Debit-note serialization is intentionally rejected until the mapping is approved against the current Romanian profile. Tax scheme is restricted to VAT and tax-category mapping is explicit. Unsupported or ambiguous categories fail with validation errors instead of being guessed.

## Mapping and endpoint requirements

The generator maps the canonical header, invoice number, issue/due dates, currency, seller and buyer snapshots, electronic endpoints, lines, quantities and units, unit prices, discounts, VAT categories, tax breakdowns, totals, and credit-note original-document reference. Date values are emitted as ISO dates, not database timestamp strings.

Each party must have an explicit `endpoint_id` and `endpoint_scheme_id`. The latter is a four-digit ISO 6523 ICD code checked by the pinned profile rules. The generator deliberately does not infer electronic endpoints or scheme codes from a CUI, VAT number, or email address. The new nullable fields preserve compatibility with existing records; generation fails with an actionable message until an approved endpoint and scheme are present. The endpoint fields are included in the canonical issued snapshot hash.

## Validation

Generation validates in this order:

1. Reconstruct the canonical issued snapshot and verify its SHA-256 before reading it as source data.
2. Render XML using DOM text nodes, not string concatenation.
3. Validate against the pinned OASIS UBL 2.1 XSD.
4. Run the pinned CIUS-RO 1.0.9 Schematron artefacts with Saxon-HE 10.9 and Java 21. The XSLT 2.0 stylesheets cannot be executed by PHP's libxslt/XSLTProcessor, which only supports XSLT 1.0.
5. Store validated XML on the private `local` disk with SHA-256, byte size, media type, and creator metadata. A verified existing artifact is reused; missing or modified artifacts are not silently replaced.

Source archive hashes and licence/provenance notes are stored beside the bundled schemas, rules, and Saxon runtime. CI installs Java 21 for the feature suite. Production must provide Java 21 and configure `EFACTURA_JAVA_BINARY` if the executable is not named `java`.

## Production gates and operator recovery

The local ruleset version is pinned for reproducibility, not claimed as a certification. Recheck the current official Ministry of Finance technical specification and profile artefacts before deployment. Run approved official test fixtures and obtain fiscal-specialist review. The app must not log credentials or full XML payloads, and this service does not send them anywhere.

If generation fails, inspect the field-level validation error and ruleset ID. Supply missing endpoint identifiers only from verified customer/supplier master data. Do not mutate issued invoice snapshots. For changed commercial or fiscal facts, use the correction-document lifecycle and generate XML from the new issued record. If a stored XML artifact fails its hash check, restore the verified bytes from backup rather than overwriting it.

No production endpoint, credentials, retry policy, submission status, or live ANAF request is configured by this implementation. Test and production submission settings must be introduced separately after official integration requirements are approved.
