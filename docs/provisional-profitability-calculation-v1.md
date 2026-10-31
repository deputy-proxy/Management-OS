# Provisional operational profitability calculation v1

**Status:** Engineering workflow implemented; not finance-approved profitability or statutory accounting output.  
**Issue:** #354  
**Policy boundary:** Finance-owner approval of allocation policy and validation against real costs remain deferred. The calculation is deliberately provisional and must not be represented as recognized revenue, statutory profit, or reconciled financial reporting.

## Allocation configuration

- Product resources are explicitly classified as `direct` or `overhead`. Existing rows remain unclassified until reviewed; no classification is inferred.
- Allocation rules currently support the explicitly configured `percentage` method only. The method, effective-from/effective-until dates, rule version, target, percentage, and expected currency are captured in each immutable allocation snapshot.
- A rule's version advances when its target, percentage, method, effective dates, or expected currency changes. Previous snapshots and calculation runs are not rewritten.
- Rules are selected against the product-resource month. A rule is active when its effective interval contains the resource period date.
- Direct and overhead percentage rules may remain partially assigned while policy is pending; every remainder stays visible as unallocated. No implicit balancing or redistribution is applied.
- Every allocation is rounded independently to two decimals using half-up rounding. Any resulting positive or negative residual is stored as an unallocated snapshot. It is not silently redistributed to another target.
- Currency must match the source currency. No FX conversion is performed.

## Provisional calculation contract

The Finance > Profitability Preview page creates immutable calculation runs for a selected date range. Each run stores its period, formula version, assumptions, exceptions, and product-level source references.

- Revenue basis: `ProductRevenue.amount_decimal` and `currency_code` only. Legacy integer amounts and missing currency are excluded, not inferred. The calculation does not assert that a product revenue row is recognized revenue or reconcile it to an invoice.
- Cost basis: the latest versioned allocation snapshots for product resources in the selected resource-month period. Costs must have an explicit `direct` or `overhead` classification and a product target to enter the corresponding product result.
- Formula: provisional contribution = canonical product revenue − allocated direct cost − allocated overhead cost. Provisional margin = provisional contribution / canonical product revenue × 100.
- Currency totals are separate. No cross-currency aggregation or conversion occurs.
- Missing revenue, missing cost coverage, unclassified sources, unknown allocations, or unallocated cost residuals prevent the run from presenting a provisional profit or margin for the affected product/currency. Missing values are not treated as zero.
- Non-product allocation targets, unknown source values, deleted sources, and invalid/unmapped inputs are retained in the run's exception list. Product results retain revenue row IDs, allocation snapshot IDs, and source resource IDs.
- Each recalculation creates a new immutable run. Corrections do not edit previous results.

## Deferred approvals and limitations

The following remain outside this engineering implementation and require explicit owner/finance decisions before the output can be treated as approved profitability:

- revenue eligibility, invoice duplication, recognition timing, credits/refunds/reversals, and period basis;
- direct-cost eligibility, unique source keys, duplicate source prevention, and shared-cost rules;
- approved overhead methods beyond percentage allocation;
- real-source mapping, completeness reconciliation, and qualified accounting review;
- final treatment of operational periods, cutoffs, and correction policy.

The current UI and run status intentionally say **provisional**. No historical records are backfilled, reclassified, or rewritten by this feature.
