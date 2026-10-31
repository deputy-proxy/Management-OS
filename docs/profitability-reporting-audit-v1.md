# Profitability reporting: current-state audit v1

**Status:** Engineering scope audit; profitability calculation remains gated on approved inputs  
**Date:** 2026-10-27  
**Roadmap:** [Issue #355](https://github.com/deputy-proxy/Management-OS/issues/355)

## Existing capability

The current finance dashboard in `App\\Services\\FinancialOverviewData` reports legacy transaction integer amounts by stored transaction type and linked account currency, transaction classification counts, canonical cash movements separately, and invoice status counts. It explicitly does not calculate a reconciled cash balance, revenue, expense, receivable/payable balance, or margin.

Product records use separate `product_revenues`, `product_resources`, and `product_costs` tables. `ProductRevenue` can link a product to a customer invoice and month. `ProductResource` stores a resource amount and currency-capable decimal columns; `ProductCost` stores allocated cost rows and currency-capable decimal columns. The additive canonical decimal/currency columns are nullable, and their presence does not prove that historical rows have been mapped or populated correctly.

`App\\Services\\ProductCostService::recalculateForResource()` currently deletes existing cost rows for a resource, recreates rows from allocation percentages using the legacy integer amount, rounds each allocation independently to an integer, and updates the resource's allocated percentage. It does not itself establish source-cost eligibility, a common monetary unit, currency compatibility, or historical calculation provenance. `ProductsOverviewData` reports product prices, availability, and data-quality states; it is not a profitability report.

## Blocking gaps and invariants

1. **Revenue semantics are not approved at the product-row level.** A product revenue row's link to a customer invoice does not establish whether it is a recognized revenue amount, whether it duplicates invoice-level reporting, or how credits and corrections relate to it.
2. **Cost eligibility and double-count prevention remain unresolved.** The existing allocation service does not prove that each source expense is eligible, uniquely attributed, or absent from another cost source. Direct-cost attribution rules in issue #353 remain a prerequisite.
3. **Legacy units and currencies are not a common basis.** Legacy integer amounts are not safely comparable with nullable canonical decimal amounts. Product resources/costs/revenues must not be aggregated across currencies or mixed across amount representations without an approved source-backed mapping.
4. **Allocation behavior can obscure residuals and history.** Independent rounding can cause allocated rows to differ from the source amount. Delete-and-recreate behavior does not itself preserve a prior calculation version. The owner decision register says percentages must total exactly 100%, historical costs must be preserved, and residuals must remain visible rather than silently redistributed; safe runtime implementation and correction behavior still need design.
5. **Period and basis are undefined for a defensible margin.** Product cost/revenue month fields exist, but their relationship to service period, invoice period, recognition date, and payment date is not a complete approved report contract. Cash and accrual operational views must remain distinct.
6. **Missing data must remain visible.** Missing product/invoice links, absent or unverified currency, unknown source units, unallocated resources, unsupported allocation percentages, and incomplete source coverage should be surfaced as exceptions. Missing costs must not be interpreted as zero cost.
7. **Traceability and authorization are required.** Every reported amount needs a reproducible source path, the applied rule/version, report cutoff and currency, with authorized drill-down and safe exception handling.
8. **Tests must reconcile to source records.** A calculation test alone is insufficient; independently computed fixtures must demonstrate source-to-report inclusion, exclusion, allocation, rounding residual, and correction behavior.

## Safe staged delivery

### Stage A: approve the calculation contract

- Approve revenue source eligibility, source references, relationship to customer invoices, and treatment of credits, refunds, reversals, and corrections.
- Approve direct-cost eligibility and uniqueness keys, shared/missing cost treatment, overhead allocation basis, exact-100% validation, and residual representation.
- Specify amount units, currency source, date basis, reporting timezone/cutoff, period correction behavior, and which values remain excluded pending mapping.
- Record accountable owner decisions and qualified finance/accounting review where accounting treatment is asserted. Owner policy alone is not statutory approval.

### Stage B: reproducible calculation

- Calculate only from source rows whose required contract fields are verified and whose rule mapping is approved.
- Keep currency totals separate; do not infer currency or rescale legacy integers.
- Preserve historical allocations and use explicit versioning or linked correction events rather than silent destructive recalculation.
- Make source-cost coverage, missing inputs, exclusions, and rounding residuals visible.
- Add service/feature tests for 100% and invalid allocations, zero and missing costs, duplicate source attribution, deleted/orphaned records, currency mismatch, boundary dates, rounding, recalculation rollback, and corrections.
- Independently reconcile sample outputs to the exact included revenue and cost source rows.

### Stage C: report and operations

- Label the reporting basis, period/cutoff, currency, formula/rule version, source coverage, and limitations in the UI.
- Provide permission-protected drill-down from aggregates to the authorized source records.
- Preserve exclusions and exception counts in exports and audit evidence.
- Do not describe an operational margin as a statutory accounting profit figure without the appropriate review.

## Decisions and review gates

The financial data contract and decision register record owner-approved target behavior for currency separation, explicit units, direct costs, overhead percentages totaling exactly 100%, visible residuals, and historical-cost preservation. They also explicitly leave field-level legacy mapping and safe implementation unresolved. Issue #353's audit records that eligible source types, uniqueness keys, period basis, shared-cost handling, and correction semantics still need explicit decisions.

Do not implement a margin by subtracting legacy product costs from product revenue or invoice totals merely because the records share a product or month. Do not treat an empty cost set as zero costs, infer missing currency, combine nullable canonical decimal columns with legacy integers, or silently recalculate historical allocations.

## Acceptance status

This audit records current product/finance capabilities and the prerequisites for reproducible profitability reporting. It does not add a margin formula, alter historical amounts, recalculate allocations, infer accounting recognition, or claim reconciled profitability. Implementation remains gated on approved revenue/cost contracts and traceable, independently reconciled fixtures.

## Issue #354 engineering delivery update

A provisional operational calculation workflow is now implemented in `docs/provisional-profitability-calculation-v1.md`. It uses only explicit canonical product-revenue values and versioned product-cost allocation snapshots, keeps direct and overhead costs separate, isolates currencies, and withholds provisional profit/margin when required coverage is incomplete. This does not remove the approval gates above: approved revenue/cost contracts, real-source mapping, and finance/accounting validation remain outstanding. The UI must continue to label all results provisional.

## Issue #355 reporting application update

The Finance > Profitability Reports page reads immutable calculation runs and supports period selection through run history, product filtering, currency filtering, per-currency totals, source references, and exception visibility. Customer drill-down is available only when both `finance-profitability.customer-view` and `customer_invoice.index` are granted. Customer attribution follows the explicit `ProductRevenue → CustomerInvoice → Customer` relationship; invoice status and invoice totals are not treated as revenue. Customer direct cost, overhead, profit, and margin are withheld because current cost snapshots do not attribute costs to customers.

Project reporting is explicitly unavailable because the calculation contract contains no project attribution. No project/customer/product relationship is used to infer it. Customer revenue source rows are rechecked against the immutable run's stored product/currency total; missing, changed, or mismatched rows suppress the affected customer breakdown. The current customer relationship itself is not historically snapshotted, so a reassignment that preserves the revenue total can still change the displayed customer grouping. This remains a source-history limitation, not an approved historical attribution report.

See `docs/profitability-reports-v1.md` for the delivered report contract and limitations.
