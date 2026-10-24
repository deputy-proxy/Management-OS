# BCR Account Statement PDF Format v1.0

**Status:** Evidence-backed specification for the observed Banca Comercială Română (BCR) Romanian-language RON account-statement layout. Scope is limited to the supplied statement issued 1 October 2026 for the period 1–30 September 2026. It is not a guarantee that every BCR product or later template uses the same layout.

- Roadmap issue: [#311 - Document Bank A statement format](https://github.com/deputy-proxy/Management-OS/issues/311)
- Source evidence: one nine-page, digitally text-extractable PDF supplied by the repository owner; account-holder and counterparty data must not be committed.
- Fixture: `tests/Fixtures/BankStatements/BankA/bcr-ron-layout-synthetic.pdf` (synthetic values; layout/labels derived from the supplied sample, not a copy of the source statement).
- Independently checked statement arithmetic: opening balance RON 359.11; period debits RON 53,283.17; period credits RON 59,323.00; closing/available balance RON 6,398.94. Check: 359.11 - 53,283.17 + 59,323.00 = 6,398.94.
- Parser status: not implemented by this issue. This document does not authorize posting imported rows.

## 1. Observed document and layout

The supplied sample identifies the issuer as **BANCA COMERCIALĂ ROMÂNĂ S.A.** and uses the Romanian title **“EXTRAS DE CONT”** (account statement). The statement header includes a statement number and issue date, statement period, account identifier (“Cont nou”), IBAN (“Cod IBAN Nou”), currency (“Produse in valuta RON”), account holder, CIC, CUI/CNP and product type. These identifying values are intentionally omitted from the committed fixture.

Transaction pages are organized into daily blocks. A block begins with `Data` and `Sold contabil initial`, then a repeated table header containing `Data operatiunii`, `Explicatie`, `Referinta Oper.`, `Debit`, `Credit` and a `Data Valorii` / `Document` subheading. Transaction details can wrap across multiple physical lines. Each daily block concludes with `Tranzactii finalizate` debit/credit subtotals and `Sold contabil final`. The document ends with period totals and `Sold contabil final la` / `Sold disponibil la` values.

The supplied file has 9 pages. Transaction content appears on pages 1–8; page 9 contains the repeated statement heading but no transaction rows. Page headers repeat on continuation pages. A future parser must not mistake repeated headers, daily subtotals, balance lines, legal text or blank continuation pages for transactions.

## 2. Confirmed fields and parsing observations

| Field / feature | Observed evidence | Status / rule for future parser |
| --- | --- | --- |
| Issuer | `BANCA COMERCIALA ROMANA S.A.` | Confirmed for this sample; reject unsupported issuers rather than guessing. |
| Locale / language | Romanian labels and Romanian date/number presentation | Confirmed for this sample only. |
| Statement issue date | `01-10-2026` in header | `DD-MM-YYYY` observed. Keep separate from statement period. |
| Statement period | `01-09-2026 - 30-09-2026` | `DD-MM-YYYY`; inclusive period interpretation is consistent with totals, but validate on more samples. |
| Account currency | `Produse in valuta RON` | RON is the account currency in this sample; do not infer it for other accounts/statements. |
| Transaction date/time | e.g. `10-09-2026 12:14` | Date `DD-MM-YYYY`, optional 24-hour `HH:MM`; some descriptions include a separate `data valutei` value date. Preserve operation date and value date distinctly. |
| Description | Romanian transaction prose and counterparty/reference text, often wrapped | Wrapped lines belong to the current transaction until the next row or block summary. Exact column geometry is PDF-layout dependent. |
| Operation reference | `Referinta` followed by an alphanumeric reference in descriptions | Distinct from the separate `Referinta Oper.` / `Document` column. Do not collapse without preserving source fields. |
| Debit / credit | Separate `Debit` and `Credit` columns | Positive amounts appear in one side, with `0,00` in the other. Store exact decimal values; do not use binary floating point. |
| Decimal/grouping format | Examples `53.283,17`, `6.398,94`, `0,51` | Romanian-style dot grouping and comma decimal separator observed. Parse strictly; malformed or ambiguous values must fail closed. |
| Daily balance | `Sold contabil initial` / `Sold contabil final` | Daily opening/closing balances are present in this sample. Verify each daily roll-forward before acceptance. |
| Period summary | `Total tranzactii finalizate pe perioada`, closing and available balance | Final totals on page 8; available balance is timestamp/context-sensitive and should remain a separate field. |
| Processing fees | Separate rows labelled `Comision Procesare ...` with debit amounts | Fee is its own transaction row in the sample, often adjacent to the related payment. Link to the payment only when an explicit reference supports it. |
| Account administration fee | `Incasare Comision de administrare...` with debit amount | Distinct fee description; preserve direction as printed rather than deriving it from Romanian verb semantics. |
| Card transaction | `Tranzactie comerciant`, masked card number, reference, merchant/location and transaction timestamp | Card rows differ from transfer rows; do not require a bank transfer reference pattern for all transactions. |
| Foreign-currency card transaction | One row describes original `174 EUR`, account amount `922.69 RON`, exchange-rate text and commission; separate conversion-fee row | Preserve original amount/currency, account-currency amount, exchange-rate text and separate fee where reliably extracted. The sample's exact FX semantics need confirmation against additional BCR statements before normalization. |
| Empty day / blank final page | Some date blocks have no transactions; page 9 is blank apart from repeated header | Valid non-transaction content; do not synthesize zero-value transactions. |
| Text versus scan | Text extraction returned selectable statement text; page images show printed table layout | This sample is digitally text-extractable. Scanned/OCR PDFs are unverified and out of scope until supplied and separately tested. |

## 3. Independently checked examples

The supplied source's period summary on page 8 reports total debits `53.283,17`, total credits `59.323,00`, and closing/available balance `6.398,94` RON. The opening balance on page 1 is `359,11` RON. The arithmetic reconciles exactly:

`359.11 - 53283.17 + 59323.00 = 6398.94`

A second cross-check from the sample: the 10 September daily opening balance is `9.110,11`, daily debit total `17.517,92`, daily credit total `31.864,00`, and daily closing balance `23.456,19`; `9110.11 - 17517.92 + 31864.00 = 23456.19`.

These checks confirm the displayed statement arithmetic, not the correctness of any particular counterparty, business classification or accounting treatment. The original statement remains the source for the above checks and is not committed. The fixture uses synthetic values to avoid exposing account and transaction details.

## 4. Format/version coverage and unresolved fields

Only one statement template and one RON current-account product were supplied. There is no evidence here for other BCR account types, currencies, exports, older/newer templates, scanned PDFs, statements with no activity throughout the period, or all possible reversal/correction types. The sample contains card purchases, instant payments, settlements and fees, but does not establish a general reversal format.

The following remain **unconfirmed** and must not be guessed in a parser:

- whether every statement has identical column positions, fonts, page sizes or labels;
- whether the operation reference or document reference is stable/unique across accounts and periods;
- the canonical relationship between payment rows and their fee rows;
- complete reversal/refund/correction semantics and their sign conventions;
- whether transaction currency can differ from account currency outside card transactions;
- how negative amounts, parentheses, zero rows, missing references and malformed dates are printed;
- whether PDFs may be scanned, encrypted, rotated, digitally signed, or contain text split across content blocks;
- whether page 9's blank continuation page is typical or specific to this export.

Version `1.0` means the observed sample is documented; it does **not** mean all BCR formats are covered. Increment the specification version when new verified layouts or materially different semantics are added.

## 5. Fixture policy

`bcr-ron-layout-synthetic.pdf` is a synthetic, redacted-by-construction fixture. Its labels and layout structure are based on the supplied BCR sample, while all account identifiers, names, transaction references and transaction amounts in the fixture are synthetic. It is not an unredacted copy and must not be used as evidence for real transaction values. The source PDF must remain outside the repository's fixtures.

Fixture companion data is `bcr-ron-layout-synthetic.json`. Expected amounts there are synthetic. Source-derived reconciliation evidence is recorded separately in this specification, with no account-holder or counterparty identifiers.

## 6. Malformed input, audit and safe recovery requirements

A future parser must stage an **unposted preview** and must never directly create or post financial transactions during PDF parsing. At minimum it must:

1. Validate PDF readability, supported issuer, supported template signature and statement period before extracting rows.
2. Reject/quarantine unreadable, encrypted, scanned-but-unsupported, unsupported-version or structurally ambiguous inputs with an actionable error code.
3. Preserve source hash, import attempt ID, template/spec version, page and row location, parser version, counts, and a safe error category in an audit event. Do not log raw PDF contents, full IBANs, names, or complete descriptions.
4. Strictly parse dates and Romanian decimal strings. Never silently strip unknown characters, coerce ambiguous separators, infer missing currencies, auto-correct signs or silently drop rows.
5. Distinguish transactions from daily subtotals, balances, period totals, repeated page headers, legal notices and blank pages.
6. Validate each daily opening/closing roll-forward and the statement-level opening + credits - debits = closing equation. Report mismatches with page/row references and safe counts, not transaction descriptions.
7. Detect likely duplicates using a documented source fingerprint; never auto-post a duplicate or assume a reference alone is globally unique.
8. Keep all rows in preview/quarantine until an authorized reviewer approves them. On failure, retain the source hash and diagnostics, allow a corrected file to be re-imported idempotently, and leave posted financial state unchanged.

## 7. Verification and operational procedure

- Confirm source file access and SHA-256 in the restricted evidence store; never commit the source PDF.
- Verify issuer, issue date, period, account currency and page count.
- Independently transcribe opening/closing balances, period debit/credit totals, selected transaction rows and each selected daily roll-forward.
- Run fixture tests and parser tests against the sanitized synthetic fixture; test malformed decimal/date strings, unsupported version, duplicate import, fee rows, multi-line descriptions, empty blocks and balance mismatches.
- Review preview and reconciliation totals before any separately authorized approval workflow.
- If extraction fails or totals disagree, quarantine the import, preserve the source fingerprint and diagnostics, correct the parser/fixture only with evidence, and retry without changing posted records.

Do not implement a parser until the remaining required format coverage and finance-owner approval are addressed by the dependent parser issue.
