# Canonical cash movement reporting v1

**Policy version:** `v1`
**Effective date:** 2026-10-27
**Scope:** Currency-separated reporting of transaction amounts that have an explicit direction and canonical decimal/currency fields.

## Inclusion contract

A transaction contributes to this report only when all of the following are true:

- it is not soft-deleted and its timestamp falls within the reporting period;
- `transaction_type_code` is exactly `money_in` or `money_out`;
- `amount_decimal` is present and non-negative;
- `currency_code` is present and matches an uppercase three-letter currency code.

The dashboard groups by currency and direction, showing current and previous month totals and counts. It never adds amounts across currencies or derives a net total by converting currencies. Rows that fail the inclusion contract remain in the excluded count and are not silently treated as zero-value cash movements.

## Meaning and limits

This is a record of directional cash movements, not accrual revenue, an accounting revenue/expense statement, profit, receivables, payables, settlement proof, or bank reconciliation. The legacy integer `amount` field is not used. A customer-linked money-in row is not automatically revenue or proof that an invoice is paid; a vendor-linked money-out row is not automatically a payable settlement. Transfer, refund, reversal, chargeback, and cross-currency pair semantics are not inferred from direction alone.

## Data quality and backfill

The report does not backfill `amount_decimal`, `currency_code`, or direction. Existing records with missing canonical fields remain excluded. Before any historical normalization, use the approved source-to-target map, preserve original values, record mapping rationale, and reconcile exceptions. Amount conversion/scaling is prohibited without source-unit evidence and an approved migration plan.

## Operational validation

Tests use known synthetic RON and EUR transactions to assert separate totals, money-in/out grouping, and exclusion of legacy rows. Production use should reconcile sample rows against source statements and account currency before this output is treated as an operational cash report.

## Filters and transaction drill-down

The consolidated report supports a reporting date range and currency filter. Canonical cash-movement rows additionally support filtering by financial account, explicit direction (`money_in` / `money_out`, or unknown direction), and the registered transaction classification. These cash-specific filters do not alter issued-document or vendor-invoice records when the report displays all source bases.

Each cash movement remains available as an individual transaction row with its source-record link, account, event timestamp, classification, direction, currency, inclusion status, and exclusion/unknown reason. Totals are calculated from the same filtered source rows and remain separated by currency. The report does not derive opening or closing balances.

## Transfers, reversals, and duplicate candidates

The current transaction schema does not store a dedicated transfer/refund/reversal event type or a linked original-transaction reference. Therefore the report does not infer those semantics from the legacy type label, payment method, amount, direction, or proximity in time. A row with a supported direction is shown only as its recorded directional cash movement; it is not reclassified as revenue, expense, settlement, transfer, or reversal, and opposite-direction rows are not netted.

Potential duplicates are flagged only when the report-relevant source fields match exactly: account, timestamp, legacy type and legacy amount, direction, canonical amount/currency, payment method, and invoice/tax references. This is a review hint, not proof of duplication. Every candidate row is retained and counted; the report never automatically deduplicates financial records. Unknown business classifications remain visibly unclassified. When direction, non-negative canonical amount, and currency are valid, the movement still contributes to the directional cash total because those are the canonical cash-source inclusion fields; the report does not invent a business category. Rows missing any of those cash fields remain visible with an unknown status and are excluded from supported amount totals.
