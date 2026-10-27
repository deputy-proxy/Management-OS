# Budget-versus-actual reporting: current-state audit v1

**Status:** Engineering scope audit; comparison contract depends on versioned budgets and approved actuals  
**Date:** 2026-10-27  
**Roadmap:** [Issue #357](https://github.com/deputy-proxy/Management-OS/issues/357)

## Existing capability

The finance overview service reports operational transaction summaries, transaction classification counts, canonical cash movement totals separately, and invoice status counts. It does not load a financial budget version, compute a budget variance, or claim that transaction classifications represent recognized revenue or expense.

The repository's existing `Project.budget` and `PaidCampaign.budget` fields are not a shared, versioned financial-budget contract. They do not establish common currency, reporting period grain, category mapping, approval status, or consistent linkage to financial actuals. Issue #356 separately audits the missing budget-entry/versioning workflow.

The financial data contract records that legacy integer amount units and some currencies remain unknown, canonical decimal/currency columns are nullable additions, cross-currency conversion is not automatic, and operational cash and accrual views must remain distinct. Product revenue and product costs also require approved source mappings and cost-attribution rules before they can serve as comparable actuals.

## Gaps and invariants

1. **No approved budget version to compare.** A report must select a specific approved version and effective period, not use an arbitrary current value or draft.
2. **Actuals are not a single compatible dataset.** Legacy transaction sums, canonical cash movements, invoice status counts, product revenue, and allocated product costs have different schemas and inclusion rules. They must not be silently combined into one actuals figure.
3. **Category mapping is not established.** Each budget dimension needs an approved mapping to supported actual categories. Unknown classifications and unmapped rows must remain visible as exceptions.
4. **Currency and units must match.** Compare only values with verified common currency and amount semantics. Do not rescale legacy integers, infer currency, or add amounts across currencies without an explicitly approved FX contract.
5. **Period/basis alignment is required.** The selected budget period must match the actuals report's approved date field, cutoff, timezone, and cash/accrual basis. Transaction timestamp, invoice month, due date, payment date, and product-cost month are not interchangeable.
6. **Variance formula and sign must be explicit.** Define whether variance means actual minus budget or budget minus actual, percentage denominator behavior, treatment of zero/missing budget, and whether favorable/unfavorable labels differ by category. Do not infer these semantics from presentation conventions.
7. **Missing actuals are not zero.** Incomplete source coverage, unresolved units/currency, absent cost data, and excluded transactions should qualify the comparison and expose counts/reasons.
8. **Revisions must be reproducible.** Preserve selected budget version, actuals query/rule version, report period, currency, and generation time so historical results can be reconstructed after later budget revisions.
9. **Drill-down must preserve permissions.** Every included/excluded amount needs an authorized source path. Aggregate report access must not bypass invoice, transaction, product-cost, or budget permissions.
10. **Tests must independently reconcile.** Use synthetic fixtures to validate selected budget version, actual source inclusion, category/currency/period grouping, variance arithmetic, rounding, missing-value handling, and drill-down authorization.

## Safe staged delivery

### Stage A: approve comparison contract

- Depend on an approved, versioned financial budget contract from #356.
- Define the actuals contract for each supported report: source tables/fields, inclusion and exclusion rules, category mapping, currency and amount unit, date basis, cutoff, and accounting/operational limitations.
- Specify variance sign, percentage formula, zero denominator, favorable/unfavorable labeling, and missing-data behavior.
- Define version selection, reproducibility metadata, exception codes, and source drill-down permissions.

### Stage B: calculate comparable rows

- Join only approved budget versions and actuals whose dimensions, periods, units, and currency are compatible.
- Keep incompatible and unmapped items in explicit exception outputs rather than coercing them to zero or dropping them silently.
- Compute variance only after its formula and rounding step are approved.
- Independently reconcile the actuals side to included source records and the budget side to the selected approved version.
- Add service/feature tests for missing budget periods, revised versions, unknown category, currency mismatch, empty actuals, zero budget, negative/adjustment entries where supported, date boundaries, and access denial.

### Stage C: present and audit

- Label selected budget version, period, basis, currency, actuals coverage, formula, generation time, and limitations.
- Show absolute and percentage variances only where the inputs satisfy the approved contract; display exceptions separately.
- Preserve enough metadata to reproduce an exported report and audit the chosen budget version.
- Do not describe an operational comparison as statutory accounting or tax reporting without qualified review.

## Decisions and review gates

The financial data contract and decision register require explicit units/currencies, no automatic FX, separate cash/accrual views, visible unknowns, and source-preserving corrections. Those rules do not by themselves define budget version selection, actuals category mapping, variance sign, or favorable/unfavorable semantics. Issue #355's profitability audit also records unresolved product revenue/cost eligibility and attribution rules.

Do not compare `Project.budget` or `PaidCampaign.budget` directly with transaction totals or product costs. Do not present a numeric variance when the selected budget and actuals differ in currency, unit, category, period, or reporting basis.

## Acceptance status

This audit identifies the contract required for a reproducible budget-versus-actual report. It does not calculate variance, import legacy budgets, infer financial classifications, or alter source data. Implementation remains gated on approved budget versioning, compatible actuals mappings, and independently reconciled test fixtures.
