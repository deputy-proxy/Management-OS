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


## Canonical normalization preview

```bash
php artisan finance:normalize-ing-statement /path/to/statement.pdf
```

The normalizer maps positive credits to `money_in` and negative source debits to `money_out`, retaining a non-negative decimal-string amount and the source RON account currency. It carries the ING bank reference, any distinct `Bank reference` value, counterparty when present, signed balance-after value, source hash, parser version and page/row provenance. The source statement has no transaction-time field, so the normalized time remains null.

Only explicit `Service Fee` rows are classified as `fee`. Rows labelled `Amount debited from credit line` are identified as `credit_line_movement` but always require review because their accounting relationship to nearby entries is not inferred. Other business types remain unresolved. Foreign-currency details and possible refund/reversal wording require review; no FX conversion, fee linkage, reversal classification, approval or posting is performed. Duplicate bank references are preserved as separate rows and reported as warnings rather than silently deduplicated.

## Reviewable import batches

The Finance **Statement Imports** page accepts the supported ING RON PDF layout, selects a financial account, and creates an auditable preview. The original uploaded PDF is deleted from temporary storage after parsing; the batch retains a normalized preview payload and source SHA-256, not the source file. Access is gated by the existing `transaction.create`, `transaction.index`, and `transaction.update` permissions.

Each batch has a unique `(financial account, source SHA-256)` key. Re-uploading the same file for the same account is rejected. Candidate transaction fingerprints are compared against prior non-rejected previews; matches are review flags only and are never silently removed. A batch with parser warnings, unresolved normalization mappings, or potential duplicate lines cannot be approved. Operators must provide a reason when approving or rejecting. State transitions are persisted as separate audit events; batches and their audit history cannot be deleted through the UI.

**Approval is not posting.** Approval/rejection records the review decision and audit history. After approval, the explicit **Complete ingestion** action persists normalized statement lines in `bank_statement_import_items`, with a deterministic per-account line fingerprint and unique database constraint. The whole batch is ingested atomically; an error rolls back all rows rather than leaving a partial import. No `transactions` rows or account balances are changed. The existing legacy `transactions.amount` field has different semantics from the canonical decimal amount, so converting ingested items into financial transactions requires a separately verified posting contract. A `completed` batch means statement lines were ingested for review, not that they were posted or reconciled. A retry is an audited return from `failed` to `previewed`; the operator must review the preview again before any subsequent decision.
