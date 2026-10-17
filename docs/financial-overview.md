# Financial Overview

The Finance Dashboard is a month-based operational summary. It only displays values supported by the current schema and does not claim to be a reconciled accounting statement.

## Metrics and definitions

| Display | Formula/source | Date basis | Currency and exclusions |
| --- | --- | --- | --- |
| Recorded transaction total | `SUM(transactions.amount)` grouped by `transaction_type` and the linked `financial_accounts.currency` | `transactions.timestamp`, current and previous calendar month | Kept separate by type and currency. Uses the stored integer units without assumed decimal scaling or FX conversion. Soft-deleted transactions are excluded. |
| Recorded transaction count | `COUNT(*)` for the same transaction groups | Same period as the transaction total | Same filters as transaction totals. |
| Customer invoice status count | `COUNT(*)` grouped by stored `customer_invoices.status` | `customer_invoices.month`, current calendar month (only ISO date values sort into the selected range; the legacy column is free text) | Customer invoices do not store currency, so invoice amounts are not aggregated or displayed. Soft-deleted invoices are excluded. |
| Vendor invoice status count | `COUNT(*)` grouped by stored status and currency | `vendor_invoices.month`, current calendar month | Status is free text, so labels are reported as stored and are not interpreted as paid/open without an agreed status vocabulary. Soft-deleted invoices are excluded. |

The previous-period comparison is the immediately preceding calendar month. The current period includes the full calendar month range, so future-dated records in the current month are included if already present in the database.

## Known schema limitations

- `product_revenues` and `product_costs` do not store currency, and their integer amount fields do not document a consistent major/minor-unit convention. They are therefore not summed into a financial dashboard margin.
- `customer_invoices` has no currency field, and its amount unit is not documented. Receivable amounts are not presented.
- Vendor invoice status is free text, so a payable balance cannot be reliably inferred from status alone.
- Transaction amounts are associated with a financial account currency, but transaction semantics do not establish whether each transaction is cash-in, cash-out, reversal, or a reconciliation entry. Totals are shown by explicit type rather than relabelled as revenue, costs, or cash balance.
- No currency conversion is performed. Totals across currencies must never be added together.

## Authorization and drill-down

The page remains protected by the existing `finance-dashboard.view` HexaLite gate. This summary exposes aggregates only and adds no record-level links that could bypass resource authorization.

## Follow-up required from Finance

Before this page can safely show revenue, costs, margin, receivables/payables amounts, or cash balances, Finance must confirm the accounting basis, amount scaling, currency requirements, transaction sign/reversal semantics, and controlled invoice status values. The current implementation intentionally avoids inventing those rules.
