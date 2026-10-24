# ING PDF parser operations

## Supported scope

`finance:parse-ing-statement` and `IngStatementPdfParser` support only the documented ING Bank N.V. Amsterdam Bucharest Branch, text-extractable RON account-statement layout in [format v1.0](ing-statement-format-v1.0.md). The parser expects the issuer/title, `Book date from ... to ...`, `Currency: RON`, summary fields, and transaction rows with a signed amount and `Balance after`. It does not support scanned/OCR PDFs, encrypted PDFs, other currencies, alternate ING products or unverified template revisions.

## Run a preview

```bash
php artisan finance:parse-ing-statement /path/to/statement.pdf
```

The command emits a safe JSON summary with source SHA-256, parser version, period, currency, opening/closing balances, signed debit total, credit total, transaction count, and warning codes with page/row locations. It does not print counterparty names, descriptions, account identifiers, references, or extracted transaction amounts. It never writes to the database or posts transactions.

## Errors and review

Errors include `source_unavailable`, `unsupported_file_size`, `not_a_pdf`, `pdf_unreadable`, `text_extraction_unavailable`, `unsupported_issuer_or_layout`, `unsupported_currency_marker`, `statement_period_invalid`, missing/malformed summary fields, `invalid_date`, `invalid_amount`, `amount_out_of_range`, and `no_transactions_extracted`. Row/reconciliation warnings include `transaction_amount_columns_unreadable`, `transaction_direction_ambiguous`, `transaction_outside_statement_period`, `transaction_balance_mismatch`, `statement_totals_mismatch`, and `statement_balance_mismatch`.

1. Keep the original report in an approved restricted location; do not commit it or paste raw text into logs/issues.
2. Record source SHA-256 and parser version.
3. Review every candidate against the source statement. Confirm row direction, amount, running balance, bank reference and description continuation.
4. Treat any warning as a failed preview. Reconcile the signed summary equation `opening balance + credits + signed debits = closing balance` and row sums before any separate approval workflow.
5. Keep bank/system entries, service fees, credit-line movements and FX metadata as separate source rows. This parser does not classify, merge, convert, approve or post them.
6. Investigate discrepancies using page/row and warning code only. Correct parser behavior against an independently verified synthetic fixture, then rerun. Re-running cannot change financial state.

## Fixture maintenance

Committed PDFs/JSON use invented values only. The source report is intentionally excluded. The fixture test contract includes signed debit and positive credit semantics, comma-grouped amounts, multiline descriptions, a blank-text counterparty field, FX detail text, a separate fee row, page continuation and balance reconciliation. These tests do not establish support for other ING formats.
