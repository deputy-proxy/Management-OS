# BCR PDF parser operations

## Scope

The `finance:parse-bcr-statement` command and `BcrStatementPdfParser` support only the documented BCR Romanian-language RON statement layout in [format v1.0](bank-a-statement-format-v1.0.md). It accepts readable, text-extractable PDFs up to 25 MB with the documented issuer, statement-period and `Produse in valuta RON` markers. Scanned/OCR statements, encrypted PDFs, other currencies and alternate layouts are not supported by this parser version.

## Run a local preview

```bash
php artisan finance:parse-bcr-statement /path/to/statement.pdf
```

The command emits a safe JSON summary containing source SHA-256, parser version, period, currency, balances, debit/credit totals, transaction count and warning codes with page/row locations. It does not print descriptions or extracted transaction references. It never writes to the database or posts transactions.

A non-zero exit code means parsing failed or one or more rows need investigation. Error codes include `source_unavailable`, `unsupported_file_size`, `not_a_pdf`, `pdf_unreadable`, `text_extraction_unavailable`, `unsupported_issuer`, `statement_period_missing`, `statement_period_invalid`, `statement_opening_balance_missing`, `statement_closing_balance_missing`, `statement_totals_missing`, `unsupported_currency_marker`, `invalid_date`, `invalid_time`, `amount_out_of_range` and `no_transactions_extracted`. Row warnings include `transaction_amount_columns_unreadable`, `transaction_direction_ambiguous`, `transaction_outside_statement_period`, `statement_totals_mismatch` and `statement_balance_mismatch`. Error output intentionally excludes raw PDF text and monetary/counterparty values.

## Review and recovery

1. Keep the source file in an approved restricted location. Do not copy real statements into the repository or attach raw statement text to logs/issues.
2. Record the source SHA-256 and parser version from the command output.
3. Review the candidate DTOs in a controlled test or preview caller; confirm every date, value date, amount direction, currency, reference and page/row against the source statement.
4. Reject the preview if the command returns a non-zero exit code, any warning exists, balances/totals do not reconcile, or a field is ambiguous. The command fails closed on warnings.
5. Preserve the source and safe error code/page-row reference for investigation. Correct parser behavior only against an independently verified fixture, then retry; no financial state is changed by retrying this command.
6. A later import-preview/approval workflow must separately stage candidates, deduplicate attempts, reconcile statement totals, record reviewer authorization and only then allow an authorized posting workflow. This parser does not implement that workflow.

## Implementation notes

PDF extraction is provided by `smalot/pdfparser`; BCR field interpretation is isolated in `BcrStatementPdfParser`; each row is returned as a typed `BankStatementTransactionCandidate`. Amounts are normalized as decimal strings without binary floating-point conversion. Source SHA-256, parser version, page number and text-row index are retained. Candidate descriptions may contain sensitive source text, so callers must not log or expose full candidate arrays by default.

The committed PDF/JSON fixture contains invented values and is for regression testing only. One owner-supplied statement was used to document the format and check statement-level and selected daily arithmetic, but parser behavior against every live BCR template, scanned PDFs, reversal semantics and foreign-currency normalization remains unverified. No candidate is automatically posted.


## Canonical normalization preview

```bash
php artisan finance:normalize-bcr-statement /path/to/statement.pdf
```

Normalization maps statement credits to `money_in` and debits to `money_out`, retains a non-negative decimal-string amount and the account currency, and carries operation/value dates, references, source hash, parser version and page/row provenance. It never converts a foreign-currency amount or infers business classifications from a payment channel or free-text description.

Only clearly labelled bank-fee rows are typed as `fee`. Other transaction types remain unresolved for review. Fee rows are linked to a principal only when the same explicit operation reference identifies exactly one money-out candidate. Unlinked/ambiguous processing fees, possible refunds/reversals and descriptions with foreign-currency details are flagged. `requires_review` blocks approval; this command never writes or posts financial records.


## Regression fixture coverage

The synthetic fixture set includes a two-page basic statement with credit/debit and a uniquely referenced fee, a locale/multiline fixture with grouped amounts and repeated operation references, and a blank-text PDF for the unsupported scanned/OCR path. Tests assert the row counts and arithmetic in these synthetic fixtures, that duplicate-looking rows are preserved with separate source-row provenance, and that ambiguous fee links are flagged. These fixtures do not establish support for alternate BCR templates; only the single owner-supplied layout documented in v1.0 is in scope.
