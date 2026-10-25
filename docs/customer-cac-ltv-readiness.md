# Customer acquisition cost and lifetime value readiness audit

## Scope

This audit identifies which current records can support customer financial analysis and which definitions or source links are missing. It does not calculate CAC or customer lifetime value (CLV/LTV), and it does not treat invoice status counts as revenue recognition.

## Existing source records

- `CustomerInvoice` stores a customer link, optional customer-contract link, month, status/status code, currency code, and amount fields (`amount_without_vat`, `vat`, and decimal variants). These are invoice records, not by themselves proof of collection, recognized revenue, refund treatment, or net revenue.
- `CustomerContract` links a customer to a contract with start/end dates and optional originating lead. Contract dates and the existence of a contract do not establish billed/collected revenue or retention.
- `CustomerLead.source` is free text. The approved source taxonomy, campaign/referral identifiers, and attribution policy are unresolved under issue #377.
- Product-level `ProductRevenue` and `ProductCost` records exist for product-margin reporting, but product revenue/cost records are not automatically a complete customer-level acquisition-cost or lifetime-value ledger.
- The reviewed customer models do not provide an approved customer acquisition-cost allocation, campaign spend-to-customer mapping, or a complete effective-dated customer churn/retention event.

## Blocking definitions and data contracts

1. **Acquisition cost numerator:** Finance must specify included cost categories (for example, campaign spend, sales labor, commissions, tools, agency fees), source system, accounting period, allocation method, currency conversion, and treatment of shared costs.
2. **Acquired customer denominator:** Sales/Finance must approve the acquisition event, deduplication rule, source attribution policy, and handling of unknown or conflicting source records.
3. **Revenue basis:** Finance must approve whether CLV uses invoiced, issued, collected, recognized, or net revenue, and define credit notes, refunds, VAT exclusion, discounts, bad debt, currency conversion, and duplicate/corrected invoices.
4. **Lifetime window and retention:** The customer cohort event, observation window, churn/retention definition, reactivation rules, and handling of contracts with gaps must be approved. Missing interactions or contract end dates cannot stand in for churn.
5. **Gross margin versus revenue:** If lifetime value is intended to mean contribution/gross profit, the applicable direct cost allocation must be approved; invoice totals alone are insufficient.
6. **Historical unknowns:** Missing attribution, currency, invoice state, or customer linkage must be reported as unknown/excluded with counts. No source field should be backfilled by inference.

## Safe boundary until approval

Current dashboards may display raw invoice status counts and source-linked invoice fields with their existing semantics. They must not label those counts as revenue, customer profitability, CAC, or CLV. Any later aggregate must state its numerator/denominator, currency and time basis, source filters, excluded/unknown rows, and provide reconciliation to source records.

## Decisions required

- Finance approves cost categories, cost allocation, invoice/revenue basis, correction/refund treatment, currency rules, and time periods.
- Sales approves customer acquisition event and source attribution policy, including legacy unknowns.
- Business owner approves retention/lifetime window and reactivation treatment.
- Data owner approves permissions, reconciliation evidence, and audit requirements.

## Validation plan after approval

Use synthetic fixtures with multiple leads per customer, unknown sources, mixed currencies, issued/paid/cancelled invoices, corrections/refunds, missing contract links, and acquisition costs shared across campaigns. Independently calculate expected values, verify rounding and currency treatment, reconcile aggregates to source IDs, and test period boundaries and permissions before enabling customer-level financial metrics.
