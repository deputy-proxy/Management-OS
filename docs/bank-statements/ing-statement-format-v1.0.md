# ING Romania account statement format v1.0

**Status:** source-observed specification; initial scope only. **Not a parser implementation.**

## Evidence and privacy

This specification was prepared from one owner-supplied 38-page ING Bank N.V. Amsterdam Bucharest Branch account statement for September 2026. The source PDF contains personal and business financial information and is intentionally not stored in the repository. No account identifiers, counterparty names, transaction references, descriptions, or source transaction amounts from the report are copied into committed fixtures.

The report's statement-level opening balance, total credits, total debits and closing balance were independently compared. The source values are intentionally omitted here. Committed fixture values are invented and reconciled independently; they are not an extract of the supplied statement. Evidence from one statement does not establish support for all ING templates or product types.

## Identifying the supported layout

The supported candidate layout has the following source markers:

- Issuer: `ING Bank N.V. Amsterdam Bucharest Branch`.
- Document title: `Account Statement`.
- SWIFT/BIC label: `BIC code (SWIFT)`.
- Period label: `Book date from DD.MM.YYYY to DD.MM.YYYY`.
- Currency and account metadata are in the account header; the observed sample is RON.
- Summary section contains `Opening Balance`, `Total Credits`, `Total Debits`, and `Closing Balance`.
- Transactions section uses columns `Book date`, `Counterparty`, `Bank Reference`, `Transaction Description`, `Debit`, `Credit`, and `Balance after`.
- Each page repeats the issuer/header and has a page number footer. Transaction descriptions and references can wrap across lines and page boundaries.

A future parser must identify the issuer and required section/column markers rather than accepting a PDF based on filename alone. Unsupported currency, missing summary fields, malformed period, unreadable text, or unknown column order must fail closed.

## Field mapping and confidence

| Field | Source rule | Confidence / caveat |
|---|---|---|
| Statement period | Header `Book date from ... to ...` | High for the observed template; reject impossible dates. |
| Account currency | Account header `Currency` | High; never infer currency from symbols or descriptions. |
| Opening balance | Summary `Opening Balance` | High for the observed template. |
| Total credits | Summary `Total Credits` | High; displayed as a positive amount in the observed sample. |
| Total debits | Summary `Total Debits` | High; displayed as a negative amount in the observed sample. |
| Closing balance | Summary `Closing Balance` | High for the observed template. |
| Book/transaction date | First column `Book date` for each transaction block | High; `DD.MM.YYYY`, but must be associated with the correct row. |
| Counterparty | `Counterparty` column | Medium; blank for some bank/system entries and may wrap. Preserve source text; do not guess. |
| Bank reference | Reference line associated with the transaction block | Medium; numeric bank sequence in the observed sample. Not guaranteed globally unique. |
| Debit | `Debit` column | High when present; observed values are signed negative, unlike BCR's unsigned debit column. Normalize direction only after validating the column. |
| Credit | `Credit` column | High when present; observed values are positive. |
| Balance after | `Balance after` column | High for row association only after the multi-line block is reconstructed; do not confuse with transaction amount. |
| Transaction description | Description and continuation lines below the row | Medium; can include payment channel, card metadata, dates, authorization code, foreign-currency amount, settlement amount/rate, instant reference and bank reference. Some lines are metadata rather than part of the description. |
| Service/transfer fees | Separate bank/system transaction rows and/or description labels | Medium; fee treatment and links to principal payments must not be inferred from proximity alone. |
| Credit-line movements | Rows labelled `Amount debited from credit line` in the observed sample | Medium; appear as separate ledger entries. Do not collapse into the adjacent payment without a documented accounting rule. |
| Foreign-currency card details | Description may show transaction currency, settlement currency and rate while account debit is in RON | Medium; preserve the account-currency ledger amount. No FX recomputation or conversion in the format adapter. |
| Reversals/refunds | No universal verified marker established from this one statement | Unknown; flag for review. |

## Locale and arithmetic

The observed statement uses `DD.MM.YYYY` dates and English-style decimal amounts: dot for decimal fraction and comma as a thousands separator (for example, the synthetic fixture uses `1,234.56`). This differs from the BCR fixture. Parse monetary values using decimal strings/minor units, not binary floating point. Reject malformed or ambiguous values rather than silently stripping punctuation.

For the source statement and every future test fixture, verify independently:

1. `opening balance + total credits + total debits = closing balance`, with total debits retaining the source's negative sign for statement-summary reconciliation.
2. Every row has at most one debit or credit amount; debit/credit sign conventions must be normalized explicitly, not inferred from whether a number happens to be negative.
3. A row's `Balance after` must reconcile with the prior running balance plus that row's signed ledger amount when the statement semantics make the running balance comparable. Credit-line and bank/system entries remain separate rows.
4. Row sums reconcile with statement summary totals, allowing no undocumented tolerance. Any discrepancy blocks approval.

## Known limits and unsupported variants

- Only one text-extractable ING Romania RON account-statement template has been examined.
- Scanned/OCR PDFs, encrypted PDFs, alternate ING products/accounts, other currencies, different column order, missing fields, and template revisions are not yet verified.
- The meaning and accounting treatment of credit-line movements, service fees, reversals, and FX-related details must be independently established before automated classification or linkage.
- No parser, normalization adapter, import approval, or posting workflow is specified by this document.

## Fixture and change policy

Use synthetic PDFs/JSON only. Mark each fixture explicitly synthetic and include expected statement totals, transaction dates, amounts, references and page/row provenance. Include multi-page continuation, grouped locale amounts, blank counterparty, bank/system rows, fee rows, credit-line movements, FX detail text, duplicate references, malformed dates/amounts, missing summary fields and balance mismatches. Do not add the original source PDF, raw extracted text, real identifiers, or real transaction rows to version control.

A format revision must increment the spec version, add a separate synthetic fixture and expected extraction contract, document field confidence changes, and independently reconcile fixture arithmetic. Parser work must remain preview-only; no automatic posting is permitted.
