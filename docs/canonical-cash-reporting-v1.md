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

This is a record of classified transaction amounts, not an accounting revenue/expense statement, profit calculation, settlement proof, or bank reconciliation. The legacy integer `amount` field is not used. A customer-linked money-in row is not automatically revenue or proof that an invoice is paid; a vendor-linked money-out row is not automatically a payable settlement. Transfer, refund, reversal, chargeback, and cross-currency pair semantics are not inferred from direction alone.

## Data quality and backfill

The report does not backfill `amount_decimal`, `currency_code`, or direction. Existing records with missing canonical fields remain excluded. Before any historical normalization, use the approved source-to-target map, preserve original values, record mapping rationale, and reconcile exceptions. Amount conversion/scaling is prohibited without source-unit evidence and an approved migration plan.

## Operational validation

Tests use known synthetic RON and EUR transactions to assert separate totals, money-in/out grouping, and exclusion of legacy rows. Production use should reconcile sample rows against source statements and account currency before this output is treated as an operational cash report.
