# Profitability reports v1

**Status:** Provisional operational reporting application implemented; not finance-approved profitability
**Issue:** #355
**Calculation formula:** operational-contribution-v1
**UI:** Finance → Profitability Reports

## Purpose and boundary

The report application reads immutable ProfitabilityCalculationRun and ProfitabilityCalculationResult records created by the issue #354 calculation workflow. It does not recalculate source data while rendering a report and does not change historical calculation runs.

Every report remains explicitly provisional. The implementation is an operational engineering view, not recognized revenue, statutory accounting profit, or a reconciled financial statement. Real-source reconciliation and finance approval of revenue/cost semantics are deferred.

## Report dimensions

- **Period:** selected through immutable calculation-run history; every run records its start and end dates.
- **Product:** product/currency result rows can be filtered by product.
- **Currency:** filters and totals are currency-specific. Different currencies are never added or converted.
- **Customer:** optional drill-down is available only from the exact revenue-row IDs stored in the selected result and the explicit ProductRevenue → CustomerInvoice → Customer relationship.
- **Project:** unavailable. Current calculation results do not contain an explicit project attribution contract. The report does not infer projects from product, invoice, customer, campaign, or other links.

## Metric definitions

| Metric | Basis | Completeness behavior |
| --- | --- | --- |
| Revenue | Sum of ProductRevenue.amount_decimal for the run's product and period, with valid currency_code | Legacy integer amounts and missing/invalid canonical inputs are excluded and surfaced. Invoice status and invoice totals are not substitutes. |
| Direct cost | Latest immutable allocation snapshots classified explicitly as direct and targeted to the product | Unknown, unallocated, excluded, or missing cost coverage is not zero. |
| Allocated overhead | Latest immutable allocation snapshots classified explicitly as overhead and targeted to the product | Unknown, unallocated, excluded, or missing cost coverage is not zero. |
| Provisional contribution | Canonical product revenue − allocated direct cost − allocated overhead | Withheld when any required revenue/cost coverage is incomplete or an affected currency has unknown/unallocated cost input. |
| Provisional contribution margin | Provisional contribution ÷ canonical product revenue × 100 | Withheld with contribution; not calculated for zero revenue. |
| Customer revenue | Reconciled canonical revenue rows grouped only through ProductRevenue → CustomerInvoice → Customer | Unattributed rows remain separate. If source rows no longer reconcile to the immutable result total, the affected customer breakdown is withheld. |
| Customer direct cost / overhead / profit / margin | Not currently supported | Always withheld. Product-level costs are not allocated to customers. |

Period totals are calculated from the selected result rows and remain separate for each currency. If any result in a currency group has an unknown component, the corresponding total is shown as unknown or withheld rather than summing only the known subset and presenting it as complete.

## Source traceability

Product results retain and display:

- product ID;
- canonical revenue row IDs;
- allocation snapshot IDs;
- source resource IDs;
- allocation IDs and rule versions;
- currency and result completeness status.

Links to product, revenue, customer, and invoice records are rendered only when the corresponding resource permission is granted. Customer drill-down itself requires both finance-profitability.customer-view and customer_invoice.index.

Customer revenue is grouped from the source rows referenced by the immutable calculation result. Before showing a customer breakdown, the report reloads those exact revenue IDs, includes soft-deleted source/invoice/customer records for historical lookup, verifies product and currency consistency, and reconciles the current canonical source amounts to the immutable result's stored revenue total. Missing rows, invalid amounts, product/currency changes, or amount differences suppress the affected product/currency customer breakdown and display a reconciliation issue.

The customer relationship is not itself snapshotted by the calculation run. A later reassignment of a revenue row's invoice/customer that leaves the amount total unchanged can change the displayed customer grouping. Customer drill-down is therefore a current explicit-source relationship view, not a fully immutable historical customer attribution ledger.

## Missing, unknown, excluded, and unallocated data

The report preserves calculation-run exceptions, including unknown revenue inputs, missing revenue/cost coverage, unknown or unallocated cost snapshots, excluded allocation targets, unclassified cost sources, and deleted or unavailable sources. Exceptions are not converted into numeric zeroes.

No exchange-rate source or FX conversion is implemented. No historical source amount is backfilled, reclassified, or rewritten by the report application.

## Authorization

- finance-profitability.view: access to the page and calculation history.
- finance-profitability.calculate: permission to create a new immutable calculation run.
- finance-profitability.customer-view: permission to request customer revenue attribution.
- customer_invoice.index: additionally required before customer attribution or invoice links are exposed.
- Product, product-revenue, and customer links are individually gated by their existing resource index permissions.

Server-side page actions enforce the calculation/view gates. Hiding customer detail in the UI is not treated as the only authorization boundary; the report service receives an explicit customer-detail flag from the page's combined permission check.

## Validation

Synthetic feature tests reconcile report totals to the calculation service, prove currency isolation and product/currency filtering, reconcile customer revenue to the underlying canonical revenue rows, preserve unattributed revenue, withhold customer costs/profit, and suppress customer amounts when source rows no longer match the immutable run.
