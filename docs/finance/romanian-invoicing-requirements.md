# Romanian invoicing requirements baseline

**Status:** research baseline for review, not approved tax/accounting policy  
**Research date:** 25 October 2026  
**Scope:** customer invoices, vendor invoices, Romanian RO e-Factura handling, correction history, retention, and the data contract needed before implementation.

This document separates requirements supported by current official sources from implementation recommendations and unresolved company-specific decisions. It is not a tax opinion. A Romanian accountant/tax adviser must approve the applicability matrix and any VAT classification before production behavior is implemented.

## 1. Executive summary

The current application models invoice records as aggregate bookkeeping rows. It does **not** yet model a legally complete invoice document or the RO e-Factura lifecycle.

The safest next step is to approve the business and tax rules first, then design the schema and workflows. Do not treat a customer/vendor table name, a free-text status, an amount column, or a successful XML upload as proof that an invoice is compliant, issued, delivered, accepted, paid, or deductible.

Key source-backed requirements for the current baseline:

- Romanian Fiscal Code Law 227/2015 governs invoice issue/content and invoice corrections, including Articles 319 and 330.
- OUG 120/2021, in its current consolidated form, defines the scope and operation of RO e-Factura. It has material changes effective in 2025 and 2026, so older guides must not be used as the authority for current deadlines or exemptions.
- The current general RO e-Factura transmission deadline is **5 working days** from invoice issuance, but not later than 5 working days from the statutory invoice-issue deadline. Scope and exceptions still depend on the transaction and taxpayer.
- Law 141/2025 changed the standard VAT rate to **21%** and the reduced rate to **11%**, effective 1 August 2025. This is not a complete list of tax categories or a substitute for product/service classification.
- Accounting Law 82/1991, Articles 23 and 25, currently states a general 5-year period calculated from 1 July of the year following the financial year for the specified electronic accounting data and supporting documents. Financial statements and other document categories have different rules. The application must not apply one blanket retention period to every artifact.

## 2. Current application gap analysis

| Area | Current implementation evidence | Gap / implication |
|---|---|---|
| Customer invoice record | customer_invoices has customer/contract IDs, integer amount_without_vat and vat, a month string, free-text status, an invoice path/reference, and identifier. Canonical decimal net/VAT and currency fields exist but are nullable. | No dedicated issue date, supply date, due date, invoice series/number pair, line items, VAT category/rate per line, exemption/reverse-charge details, correction reference, or e-Factura submission lifecycle. |
| Vendor invoice record | vendor_invoices has vendor/contract IDs, integer net/VAT values, currency, month string, free-text status, invoice number/identifier, and nullable canonical decimal values/currency. | Same document-level and line-level gaps. Legacy amount units and currency semantics must not be guessed. |
| Customer identity | customers stores name, customer type, a combined cnp_cui field, address, email and phone. | Country, locality, postal code, structured tax/VAT identifiers and legal-registration identifiers are not represented distinctly. The invoice must preserve a point-in-time party snapshot rather than silently changing when the customer master changes. |
| Vendor identity | vendors currently stores name, category, dates and website. | No reliable supplier tax/VAT identifiers or postal address snapshot. Vendor invoice receipt cannot assume these details exist in the vendor master. |
| VAT | Invoice rows have aggregate VAT amounts. A normalization config exists, but invoice-total semantics are not approved. | No line-level tax category/rate, taxable base per category, exemption reason/code, reverse-charge category, or effective-dated rate rules. Do not infer tax treatment from a single vat field. |
| Numbering | Invoice identifiers/numbers exist as strings, with no approved series policy visible in the invoice schema. | Need an approved numbering procedure, uniqueness scope, sequence allocation, concurrency behavior and void/correction policy. Do not assume a reset per year or that database IDs are legal invoice numbers. |
| Status | status is free text; status_code is nullable. | Draft, issued, submitted, accepted by system, rejected, corrected, cancelled, paid and overdue are different states. They must not be collapsed into one status. |
| Electronic invoice | No end-to-end RO e-Factura document/submission record was found in the current invoice models. | Need original XML, canonical payload hash, submission ID, timestamps, validation result, response/receipt, retry state, and a link to any correction. PDF is not a substitute for the system's original XML artifact where RO e-Factura applies. |
| Retention and audit | Invoice models use soft deletes, but no invoice-specific immutable correction/submission history is present in the inspected model. | Issued invoices and their electronic evidence must remain traceable. Corrections should create linked records/events rather than overwrite the original document. Retention and deletion controls require an approved policy. |

## 3. Legal baseline and applicability matrix

The legal source establishes the rule; the reviewer must establish whether it applies to the company's actual transaction. “Required field” below means a candidate data contract item, not a claim that every field is mandatory for every invoice type.

| Topic | Source-backed baseline | Product implication | Status / owner |
|---|---|---|---|
| Invoice issue and mandatory content | Fiscal Code Law 227/2015, Article 319, defines invoice issue and content. The normal invoice data contract includes a unique sequential invoice number, issue date, supplier and customer identifiers/addresses, description and quantity of goods/services, relevant supply date where different, taxable base, unit price/discounts, VAT category/rate/amount or the relevant exemption/special-scheme indication, and required references/mentions. Exact content varies by transaction and exception. | Create an explicit invoice header plus line items and tax breakdowns. Validate the applicable field set by invoice profile. Do not infer missing seller details from an unrelated vendor/customer master record. | **Needs accountant approval** of profiles and exceptions. |
| Invoice issue deadline | Fiscal Code Article 319(16) contains the statutory invoice-issue deadline and exceptions. The common rule is issuance by the 15th day of the month following the month of the chargeable event, subject to specific cases. | Store supply_date/tax-point date separately from issue_date; calculate due dates only from a reviewed rule set. | **Needs tax review** for all applicable transaction types. |
| RO e-Factura domestic B2B | OUG 120/2021, Article 10, requires system transmission for covered B2B transactions between taxable persons established in Romania when the place of supply is Romania, subject to statutory exceptions. The article contains specific exceptions; a generic B2B=true switch is insufficient. | Determine applicability from seller/buyer establishment, tax status, place of supply, transaction type and exceptions. Record why a transaction is exempt/out of scope. | **Needs scope confirmation** for company activities and customers. |
| RO e-Factura B2C | OUG 120/2021, Article 10^1, provides a B2C transmission regime from 1 January 2025, with exceptions such as qualifying simplified fiscal receipts. The current consolidated text also contains rules for individuals who do not provide a tax identifier and special treatment for providers identified by CNP. | Model consumer identity mode and the applicable e-Factura rule separately. Do not automatically substitute an identifier without reviewed logic. | **Needs accountant/tax adviser review**, especially CNP and consumer-identification cases. |
| RO e-Factura deadline | Current consolidated OUG 120/2021, Articles 10(7) and 10^1(2^1), sets a 5-working-day transmission deadline from issue, capped by 5 working days after the statutory issue deadline. The consolidated text reflects the 2025 amendment effective 1 January 2026. | Store issue deadline, submission deadline, attempted-at, submitted-at and response timestamps. Use Romanian working-day/holiday rules, not a simple calendar-day addition. | Rule identified; **implementation calendar and exception review pending**. |
| B2G / public-sector transactions | OUG 120/2021 includes additional rules for public procurement/public-sector transactions. | Keep a separate B2G applicability profile; do not assume the B2B/B2C rule covers public procurement. | **Applicability not confirmed**. |
| XML format and validation | RO e-Factura uses XML based on EN 16931 and the Romanian RO_CIUS rules. ANAF's invoice-completion tool exposes structured header, seller, buyer, line, VAT and total fields. The current technical specifications at the Ministry/ANAF portal are the operational source. | Treat XML as a structured, validated document, not a PDF export. Pin the current specification/version and validation rules in implementation documentation; retain the exact submitted payload and system response. | Technical implementation owner to verify current production spec before coding. |
| VAT rates and categories | Law 141/2025 changed the standard rate to 21% and the reduced rate to 11% from 1 August 2025. Special categories, exemptions, reverse charge, cross-border place-of-supply rules and transitional provisions still affect treatment. | Use an effective-dated VAT category/rate catalog and per-line tax treatment. Do not hard-code “21% or 11%” as the only possible tax cases. Never decide a rate from a generic product name without an approved classification rule. | **Tax adviser must approve category mapping and effective dates**. |
| Currency and VAT reporting | The invoice currency and VAT reporting-currency rules are distinct. Applicable currency conversion and tax-point exchange-rate rules must follow the Fiscal Code and transaction profile. | Store invoice currency, monetary values with decimal precision, VAT reporting currency/amount where applicable, and the exchange-rate source/date used. | **Needs accountant approval**; no implicit FX calculation. |
| Corrections / credit notes | Fiscal Code Article 330 governs invoice correction. The exact correction procedure depends on the situation; it is not safe to treat an issued invoice as an editable draft. | Preserve the original issued invoice. Create a correction/credit-note record with original invoice reference, reason, issue date, corrected lines/tax breakdown and its own e-Factura lifecycle where applicable. | **Needs accountant approval** of correction scenarios and status transitions. |
| Numbering and document controls | Fiscal Code Article 319 and Order 2634/2015 on financial-accounting documents are the legal baseline for invoice numbering and internal document procedures. The exact series policy is an entity procedure, not something to infer from an auto-increment ID. | Finance must approve series, sequence scope, authorized issuer, concurrent issuance behavior, gap/void handling, and how failed/unissued drafts affect numbering. Enforce uniqueness in the database. | **Business procedure approval pending**. |
| Electronic evidence and retention | Accounting Law 82/1991, Articles 23 and 25, specifies a 5-year period calculated from 1 July of the following year for the covered electronic accounting data and supporting documents. Article 28 and other rules give different periods for other categories. ANAF guidance identifies the XML with the Ministry's electronic seal as the original e-invoice artifact. | Define retention per artifact category: issued invoice XML, signature/seal, submission/validation response, rendered PDF, correction links, source evidence and audit events. Preserve original bytes/hash. Do not apply one generic deletion period to all records. | **Accountant/legal owner must approve the retention schedule**. |

## 4. VAT scenarios the design must explicitly support

These are scenario categories for requirements and tests, not an exhaustive legal tax decision tree.

1. **Domestic taxable sale, standard rate.** The correct rate depends on the tax point and the item/service classification. Current headline standard rate is 21% from 1 August 2025.
2. **Eligible reduced-rate supply.** Current headline reduced rate is 11% from 1 August 2025, but eligibility and exclusions must be determined by an approved classification rule.
3. **Exempt or zero-rated treatment.** Store the category and the required legal/exemption reason or code. A numeric VAT amount of zero is not enough to identify the legal treatment.
4. **Reverse charge.** Store the relevant tax category and mandatory invoice mention. Do not calculate output VAT as if it were an ordinary domestic taxable supply.
5. **Intra-Community supply/acquisition.** Validate the parties' relevant VAT IDs, place-of-supply treatment, evidence and e-Factura exceptions before selecting the invoice profile.
6. **Export / non-EU cross-border supply.** Tax treatment depends on the transaction facts and required evidence. Do not map every foreign customer to “zero VAT”.
7. **B2C / natural-person recipient.** Distinguish a person who supplies a tax identifier from a person who does not, and account for the current consolidated e-Factura rules and exceptions.
8. **Simplified fiscal receipt / simplified invoice.** Apply the specific statutory exception only after the issuer and receipt satisfy the legal conditions.
9. **Correction, credit note, partial credit or return.** Preserve the original invoice and link the correction. Recalculate tax only under the approved correction rules.
10. **Foreign-currency invoice.** Preserve invoice currency and the legally applicable VAT reporting conversion independently.

No invoice should be issued automatically from these scenario names alone. Finance must define the required input facts, allowed outcomes, required evidence, and failure behavior for each scenario.

## 5. Recommended data contract before implementation

### Invoice header

- Internal immutable ID and invoice direction (customer/issued or vendor/received).
- Invoice number and series as separate fields, with a uniqueness constraint and approved sequence scope.
- Issue date, supply/tax-point date where relevant, due date where relevant, and original/correction reference.
- Supplier and buyer legal names, structured addresses, country, relevant tax/VAT identifiers, and snapshot provenance.
- Invoice currency; VAT reporting currency/amount and FX metadata where applicable.
- Document-level net total, VAT total by category, gross total, paid amount and outstanding amount only where their accounting semantics are approved.
- Invoice profile / applicability decision: B2B, B2C, B2G, cross-border, simplified exception, or other approved profile.
- Workflow state kept distinct from payment state and e-Factura transmission state.

### Invoice lines and tax breakdowns

- Description, quantity, unit of measure, net unit price, discounts/charges, net line total.
- Tax category, effective tax rate, taxable base and tax amount by category.
- Exemption reason/code, reverse-charge/special-scheme mentions and relevant references when required.
- Currency/rounding rules and explicit decimal precision. Never derive a VAT category from a single aggregate vat column.

### RO e-Factura submission evidence

- Immutable generated XML and cryptographic hash.
- Specification/validator version.
- Applicability decision and reason.
- Submission ID, attempt timestamps, transport/API result, validation result and official response/receipt.
- Explicit retry/unknown outcome state so a network timeout cannot create a second legal invoice or silently lose a successful submission.
- Rendered human-readable copy as a derivative artifact, not the source of truth where the XML is the original.
- Correction links and retention metadata.

### Audit and state transitions

- Preserve original issued documents. Do not update their monetary lines or legal identifiers in place.
- Record who created, reviewed, issued, submitted, corrected and cancelled a document, with timestamps and reason.
- Separate draft, issued, submission_pending, submitted, accepted, rejected, corrected and cancelled only after Finance approves exact meanings and transition rules. An accepted transport/validation response must not be interpreted as tax approval or proof of payment.
- Keep payment_status separate from invoice and e-Factura statuses.

## 6. Business preferences versus legal obligations

| Decision | Classification | Proposed owner | Status |
|---|---|---|---|
| Which customer/vendor invoice profiles the company needs | Business scope with legal consequences | Finance owner + accountant | Pending |
| Series naming, issuer permissions, and sequence allocation procedure | Internal procedure subject to legal controls | Finance owner | Pending |
| Whether drafts consume a number, and how voided numbers are recorded | Internal procedure; must preserve numbering traceability | Finance owner + accountant | Pending |
| Default payment terms, reminder cadence, and internal approval routing | Business preference unless contract/law dictates otherwise | Finance owner | Pending |
| Required attachment types and document storage UX | Business preference plus evidence/retention requirements | Finance owner + legal | Pending |
| VAT categories, rates, exemptions, reverse-charge rules, FX method and effective dates | Tax/legal determination | Romanian tax adviser/accountant | **Not approved; block automated tax decisions** |
| B2B/B2C/B2G and CNP-based RO e-Factura applicability | Legal determination based on taxpayer and transaction facts | Romanian tax adviser | **Not approved; block automatic exemptions** |
| Correction/credit-note rules and invoice lifecycle transitions | Legal/accounting determination | Accountant + legal | Pending |
| Retention schedule by artifact type | Legal/accounting determination | Accountant + legal / records owner | Pending |
| Technical XML profile and current validator/API version | Official technical requirement | Engineering, validated by Finance | Verify before implementation |

## 7. Minimum acceptance scenarios for the future implementation

Each case should have expected header fields, line tax breakdown, legal mentions, e-Factura applicability/deadline, persisted evidence, and a test asserting no unintended state mutation.

- Domestic B2B sale at the current standard rate.
- Eligible reduced-rate sale with an effective-date check.
- Exempt and reverse-charge invoices with mandatory category/mention validation.
- B2C invoice where the recipient supplies an identifier, and where they do not.
- CNP-identified supplier/customer and other special statutory exception cases, with reviewer-approved expectations.
- B2G/public procurement profile if the business scope requires it.
- Intra-Community supply, acquisition and export examples.
- Simplified fiscal receipt exception and a normal invoice for the same customer profile.
- Invoice issued near a month boundary, with Romanian working-day deadline calculation and the statutory issue deadline.
- XML schema/business-rule rejection, network timeout with unknown outcome, successful submission response, and duplicate retry.
- Credit note / correction that references the original invoice and preserves both records.
- Two concurrent issuances to test unique sequential numbering.
- Foreign currency with VAT reporting conversion metadata.
- Retention/export of original XML, official response, rendered copy and audit history.
- Existing legacy invoice with missing canonical amount, currency, tax ID or dates: it must be flagged for review, not silently normalized or issued.

## 8. Synthetic acceptance fixtures and independent arithmetic

These values are synthetic arithmetic fixtures, not advice about which VAT treatment applies to a real product/service. A tax reviewer must approve the category before a production rule uses it.

| Fixture | Inputs | Independently reconciled expectation | Required behavior |
|---|---|---|---|
| Standard-rate arithmetic | Net 100.00 RON, assumed eligible for the 21% standard rate | VAT = 100.00 × 0.21 = 21.00 RON; gross = 121.00 RON | Preserve the tax category/rate and line calculation; do not infer standard-rate eligibility from the amount. |
| Reduced-rate arithmetic | Net 100.00 RON, assumed eligible for the 11% reduced rate | VAT = 100.00 × 0.11 = 11.00 RON; gross = 111.00 RON | Require approved product/service eligibility and effective date. |
| Partial payment | Confirmed invoice gross 121.00 RON; payment 60.00 RON; no fees or other allocations | Unallocated invoice amount by arithmetic = 121.00 − 60.00 = 61.00 RON | Show partial payment; do not mark paid or post ledger entries unless the approved settlement workflow says so. |
| One payment split across invoices | Payment 150.00 RON; reviewed invoice allocations 100.00 and 40.00; separately evidenced fee carve-out 5.00 | 100.00 + 40.00 + 5.00 = 145.00 RON allocated; 150.00 − 145.00 = 5.00 RON unallocated | Reconcile source-payment totals independently. Invoice outstanding balances remain unverified until invoice-total semantics are approved. |
| Partial correction | Original example gross 121.00 RON; reviewer-approved correction reduces net by 20.00 RON at the same assumed 21% rate | VAT reduction = 4.20 RON; correction gross = 24.20 RON; residual gross = 96.80 RON | Preserve original and correction with a reference; this arithmetic is not a generic credit-note rule. |
| Unmatched bank payment | Payment 75.00 RON; no reliable invoice evidence | 0.00 RON allocated; 75.00 RON remains unallocated | Keep the source payment unchanged and flag for review; do not guess a target. |
| Duplicate submission | Same issuer, invoice identity and XML payload hash submitted twice after a timeout | One legal invoice record and one idempotent submission identity; inspect official response before retrying | Do not create a second invoice or assume timeout means failure. |
| Invalid XML | Missing required invoice number, issue date, party data or line/tax fields | Validator rejects the payload; no issued/accepted state is claimed | Retain the draft and validation result; require correction before resubmission. |
| Currency mismatch | Invoice in EUR, payment/allocation in RON, no approved FX rule/evidence | No automatic cross-currency allocation or comparison | Preserve both currencies and route to reviewed FX handling. |

The synthetic fixtures are stored in tests/Fixtures/Finance/romanian-invoicing-requirements.json. RomanianInvoicingRequirementFixturesTest checks scenario coverage and independently recomputes VAT, gross, partial-payment, split-allocation and correction arithmetic using decimal math. Application-level invoice/e-Factura behavior tests remain blocked until the invoice header, line, tax, numbering and submission models are approved. These arithmetic checks do not establish legal tax eligibility.

## 9. Open questions that block automatic legal/tax behavior

1. Which legal entity/entities issue invoices, and what are their VAT registration statuses and tax identifiers?
2. Which products/services are supplied, and who owns the tax-category mapping?
3. Which customer/vendor and transaction profiles are in scope: domestic B2B, B2C, B2G, intra-Community, export, reverse charge, exempt and simplified cases?
4. Does the company use cash accounting, special VAT schemes, or other regimes that change invoice content/timing?
5. What is the approved invoice numbering series policy and how are voids/corrections handled?
6. Which official RO e-Factura API/specification version is deployed, and which response means the invoice is accepted into the system?
7. What source document/attachment is authoritative for received vendor invoices, and how is it matched to the received XML?
8. Which retention period applies to each record/artifact category, considering the current accounting law and any sector-specific rules?
9. What is the approved invoice-total formula for each invoice type? The current normalization configuration leaves customer/vendor invoice semantics unresolved.
10. Who signs off the requirements matrix and the test fixtures?

## 10. Official sources reviewed

All links below are official Romanian or EU sources. Current consolidated text takes precedence over older explanatory guides. Accessed 25 October 2026.

1. [Romanian Fiscal Code, Law 227/2015, current consolidated text](https://legislatie.just.ro/Public/DetaliiDocument/309361) — especially Articles 319 (invoices), 330 (correction), VAT title and Article 291.
2. [OUG 120/2021, current consolidated RO e-Factura law](https://legislatie.just.ro/Public/DetaliiDocument/310662) — Articles 5, 9^1, 10, 10^1, 10^5 and 10^10; current text includes 2025 and 2026 amendments.
3. [Law 141/2025](https://legislatie.just.ro/Public/DetaliiDocument/305340) — Article 291 rate amendments and the relevant effective-date provisions.
4. [Accounting Law 82/1991, consolidated text](https://legislatie.just.ro/Public/FormaPrintabila/00000G0LU1UD1B60JFG2VL7GE572FFBF) — Articles 23 and 25 on covered electronic accounting data and supporting-document retention, and Article 28 for financial statements/reportings.
5. [Order 2634/2015 on financial-accounting documents](https://legislatie.just.ro/Public/FormaPrintabila/00000G3Q6PDT4EEEENW3G12HPJOS9XBL) — internal financial-document procedure and retention schedule; confirm the current annex/category applicable to each artifact.
6. [Order 1366/2021, RO_CIUS technical specification](https://static.anaf.ro/static/10/Anaf/legislatie/OMF_1366_2021.pdf) — Romanian Core Invoice Usage Specification and EN 16931 baseline.
7. [ANAF electronic invoice completion tool: general invoice data](https://www.anaf.ro/CompletareFactura/faces/factura/informatiigenerale.xhtml), [seller](https://www.anaf.ro/CompletareFactura/faces/factura/vanzator.xhtml), [buyer](https://www.anaf.ro/CompletareFactura/faces/factura/cumparator.xhtml), and [invoice lines](https://www.anaf.ro/CompletareFactura/faces/factura/produse.xhtml) — official field-level examples; the tool's current technical documentation points to [efactura.mfinante.ro](https://efactura.mfinante.ro).
8. [ANAF 2025 service catalogue, RO e-Factura](https://static.anaf.ro/static/10/Anaf/Informatii_R/Catalogul_serviciilor_ANAF_2025.pdf) — describes the structured XML workflow, original XML with the Ministry's electronic seal, and SPV/API channels. It is operational guidance, not a replacement for current legislation or technical specifications.
9. [EU VAT Directive 2006/112/EC](https://eur-lex.europa.eu/eli/dir/2006/112/oj) — Articles 218, 226, 230, 244 and related provisions; use together with Romanian implementing law.

## 11. Review record

- Named Romanian accountant/tax adviser: **not assigned**. Required reviewer role: qualified Romanian accountant/tax adviser. Approval: **pending**.
- Finance owner / numbering and approval procedures: **pending**.
- Legal reviewer for exceptions, correction and retention edge cases: **pending**.
- Engineering owner for XML/API/version pinning: **pending**.
- Implementation approval: **not granted**. This document defines a reviewable baseline and does not authorize tax decisions or production invoice issuance.
