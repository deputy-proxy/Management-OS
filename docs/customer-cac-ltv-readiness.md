# Customer acquisition cost and lifetime value readiness audit

## Scope

This audit identifies which current records can support customer financial analysis and which definitions or source links are missing. The application now includes a versioned, permission-gated CAC/LTV framework, but its customer-level policy gates remain unapproved by default. It does not publish finance-approved CAC or customer lifetime value.

## Implementation status

The operational framework is available in the Finance cluster:
- evidence-backed, append-only acquisition cost entries and explicit retention observations;
- retention coverage and a churn-date cutoff for LTV when the retention policy version is approved; reactivation remains withheld pending an approved reactivation rule;
- immutable calculation runs with configuration snapshots and source IDs;
- explicit missing/unknown/shared-cost and revenue-coverage exceptions;
- currency-separated CAC/LTV results, with values withheld when policy or source coverage is unavailable;
- a permission-gated report page and synthetic regression tests.

See [the framework policy](customer-unit-economics-policy.md). Its supported test configuration is not an approved business policy. Production defaults keep customer attribution, deduplication, CAC basis, revenue basis, lifetime window, and retention basis set to `unapproved`.

## Existing source records

- `CustomerInvoice` stores a customer link, optional customer-contract link, month, status/status code, currency code, and amount fields (`amount_without_vat`, `vat`, and decimal variants). These are invoice records, not by themselves proof of collection, recognized revenue, refund treatment, or net revenue.
- `CustomerContract` links a customer to a contract with start/end dates and optional originating lead. Contract dates and the existence of a contract do not establish billed/collected revenue or retention.
- Lead-level source taxonomy and immutable attribution snapshots are implemented under `docs/sales-attribution-policy.md`. They do not approve customer-level credit across multiple leads or prove campaign spend.
- Product-level `ProductRevenue` and `ProductCost` records exist for product-margin reporting, but product revenue/cost records are not automatically a complete customer-level acquisition-cost or lifetime-value ledger.
- The new `CustomerAcquisitionCostEntry` ledger captures explicit, evidence-backed cost entries. `CustomerRetentionObservation` captures explicit retained/churned/reactivated/unknown observations with policy version and evidence. Neither ledger infers missing source facts; shared costs and missing retention evidence remain excluded.

## Blocking definitions and data contracts

1. **Acquisition cost numerator:** Finance must specify included cost categories (for example, campaign spend, sales labor, commissions, tools, agency fees), source system, accounting period, allocation method, currency conversion, and treatment of shared costs.
2. **Acquired customer denominator:** Sales/Finance must approve the acquisition event, customer-level attribution policy, identity/deduplication rule, and handling of unknown or conflicting source records. The lead-level first-touch snapshot is not automatically customer-level attribution.
3. **Revenue basis:** Finance must approve whether CLV uses invoiced, issued, collected, recognized, or net revenue, and define credit notes, refunds, VAT exclusion, discounts, bad debt, currency conversion, and duplicate/corrected invoices.
4. **Lifetime window and retention:** The customer cohort event, observation window, churn/retention definition, reactivation rules, and handling of contracts with gaps must be approved. Missing interactions or contract end dates cannot stand in for churn.
5. **Gross margin versus revenue:** If lifetime value is intended to mean contribution/gross profit, the applicable direct cost allocation must be approved; invoice totals alone are insufficient.
6. **Historical unknowns:** Missing attribution, currency, invoice state, or customer linkage must be reported as unknown/excluded with counts. No source field should be backfilled by inference.

## Safe boundary until approval

The report framework can record source-backed costs, explicit retention observations, and immutable calculation runs. It deliberately withholds CAC/LTV unless the corresponding policy switches are explicitly configured and every cohort customer has valid retention evidence. Shared costs, missing attribution, and incomplete revenue coverage remain unknown or excluded. Operational ProductRevenue is not presented as invoiced, collected, or recognized revenue.

## Decisions required

- Finance approves cost categories, cost allocation, invoice/revenue basis, correction/refund treatment, currency rules, and time periods.
- Sales approves customer acquisition event and customer-level source attribution policy, including legacy unknowns and multiple leads per customer.
- Business owner approves retention observation states, evidence requirements, lifetime window, churn cutoff, and reactivation treatment.
- Data owner approves permissions, reconciliation evidence, and audit requirements.

## Validation plan after approval

Use synthetic fixtures with multiple leads per customer, unknown sources, mixed currencies, issued/paid/cancelled invoices, corrections/refunds, missing contract links, and acquisition costs shared across campaigns. Independently calculate expected values, verify rounding and currency treatment, reconcile aggregates to source IDs, and test period boundaries and permissions before enabling customer-level financial metrics.
