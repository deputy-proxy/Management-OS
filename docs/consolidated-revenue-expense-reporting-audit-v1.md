# Consolidated revenue and expense reporting: current-state audit v1

**Status:** Engineering scope audit; not a statutory accounting approval  
**Date:** 2026-10-27  
**Roadmap:** [Issue #349](https://github.com/deputy-proxy/Management-OS/issues/349)

## Existing capability

`App\\Services\\FinancialOverviewData` exposes a monthly operational summary. It groups legacy transaction integer amounts by stored transaction type and linked financial-account currency, reports transaction classification counts, reports canonical cash movements separately, and counts customer/vendor invoice statuses. These are distinct data views; they are not currently a consolidated revenue-and-expense statement.

The transaction classification v1 contract classifies legacy type plus explicit direction. It does not establish revenue recognition, expense recognition, receivable/payable position, tax treatment, or settlement. Canonical cash movement reporting includes only rows with supported direction and present non-negative decimal amount plus a three-letter uppercase currency format; excluded rows remain counted separately. That report is a cash-movement view, not an accrual or profit report.

## Observed boundaries and gaps

1. **Transaction totals are not revenue/expense totals.** Customer/vendor labels and money-in/out direction alone do not establish revenue or expense recognition. Refunds, reversals, fees, transfers, tax movements, and corrections need their approved linked-event semantics before inclusion.
2. **Legacy units remain unresolved.** The existing transaction summary sums integer `transactions.amount`. Do not relabel or combine those sums as monetary revenue/expense until field-level source units have been evidenced and approved.
3. **Canonical cash and legacy summaries are separate contracts.** Do not add their totals together. Canonical cash reporting excludes records missing required canonical fields; the legacy summary uses a linked-account join. Their counts and totals therefore have different inclusion rules.
4. **Invoice data is not a common monetary basis.** Customer invoice amounts are integer fields without a currency column; vendor invoices have their own amount fields and currency. Status counts are useful operational context but do not prove invoice totals, recognition, or settlement.
5. **Reporting basis and date need explicit definitions.** The owner decision register calls for separate, clearly labeled cash and accrual views and Europe/Bucharest reporting boundaries. Each view must identify its selected source date and cutoff; transaction timestamps, invoice month, due date, and payment date are not interchangeable.
6. **No cross-currency totals.** Report currencies separately. A currency-code format check is not validation of a real currency, and account currency does not prove the currency/unit of every legacy amount.
7. **Traceability is required.** Each aggregate needs an explainable inclusion/exclusion contract and drill-down to source records, with permission checks and no sensitive monetary values in routine logs.
8. **Incomplete data must remain visible.** Missing classifications, missing canonical amounts/currency, invalid references, unknown status values, and unresolved source units should be exposed as exceptions or exclusions, not silently coerced to zero or hidden.
9. **Reversals and corrections need deterministic treatment.** Preserve source history and link correction events to originals. Avoid counting both original and correction as independent positive activity where the approved rule says otherwise; do not invent that rule in the report layer.

## Safe staged delivery

### Stage A: report contract

- Define separate operational cash and accrual report contracts, each with the business question, source fields, date basis, inclusion/exclusion rules, currency grouping, and limitations.
- Map each candidate source table and field to the report contract; document unknown units and unsupported semantics.
- Define stable exception codes and countable exclusion reasons, including legacy rows excluded from canonical monetary totals.
- Agree on authorized drill-down and safe record references.

### Stage B: reproducible aggregation

- Implement only report categories supported by an approved source-to-report mapping.
- Keep currency groups separate and reject unsupported mixed-currency aggregation.
- Preserve legacy summary behavior unless a separately reviewed migration explicitly changes it.
- Add service and feature tests for period boundaries, soft-deleted rows, missing/unsupported classification, absent canonical amount/currency, orphaned account references, refunds/reversals, and multiple currencies.
- Independently reconcile sample report totals to the exact included source rows.

### Stage C: presentation and operations

- Label cash versus accrual basis, reporting period/time zone, currency, source coverage, and exclusions directly in the UI.
- Provide authorized drill-down and a timestamped audit of report scope/version.
- Do not claim statutory accounting or tax compliance without qualified review.

## Decisions and review gates

The company-owner decision register records the intended separation of cash and accrual views, explicit reporting dates, and currency separation. It also states that owner decisions are not independent statutory accounting/tax approval.

Before reporting recognized revenue, recognized expenses, tax effects, or profitability as accounting outcomes, record the relevant qualified finance/accounting review and approved examples. Before changing historical totals, require an exact source-backed amount-unit mapping, a reversible migration plan, and independent before/after reconciliation. Do not infer a VAT rate or rewrite invoice values from labels alone.

## Acceptance status

This audit identifies the existing dashboard contracts and the gaps blocking a defensible consolidated revenue/expense report. It does not add totals, change source data, infer accounting semantics, or claim that the current dashboard is an accounting statement.
