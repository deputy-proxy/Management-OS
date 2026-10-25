# Executive KPI catalogue readiness audit

## Scope and status

This is an evidence-based inventory of metrics already exposed by overview services and the decisions needed before adding configurable executive KPIs. It is not a business-approved KPI catalogue. Existing cards should not be treated as approved financial, HR, or operating policy merely because they render in a dashboard.

## Existing metric inventory

| Source | Current output | Known semantics and limitations |
| --- | --- | --- |
| `AdminOverviewDashboardData` | Student, employee, customer, open lead, project, open task, and content-record counts; overdue tasks; recent activity. | Permission-scoped inventory. Several models have no reliable active/inactive lifecycle. Task visibility follows assigned-to/created-by scope unless `task.view_all` is granted. Content types remain separate. |
| `GeneralOverviewData::employeesPresent` | Employees with a timesheet covering the current instant. | This is active-timesheet presence, not a verified physical check-in. Application timezone controls the current date. |
| `GeneralOverviewData::averageCourseMargin` | Unweighted average of per-course margin percentages for the current month. | Includes courses with positive recorded revenue and at least one cost row. Cost basis needs Finance approval; revenue and allocated cost lack a consistent currency contract. |
| `GeneralOverviewData::averageFeedback` | Average non-null customer-interaction ratings for the current month, plus response count. | Rating scale and interpretation are not defined in the schema. It is not a student feedback score. |
| `GeneralOverviewData::weeklySchedule` | Sessions whose start time falls in the current calendar week. | Unified session source; status is not used to exclude records because lifecycle semantics are inconsistent. This is a schedule, not an attendance/performance KPI. |
| `FinancialOverviewData::summary` | Transaction totals/counts by account currency and transaction type for current and previous months; customer/vendor invoice status counts. | Transactions are not combined across currencies or with invoice/product amounts. Invoice status counts are not revenue or cash collected. |
| `TasksAndProjectsData` | Task counts/statuses, overdue/upcoming work, assignee open/overdue counts, project/phase date-window counts, schedule-date comparisons. | Task counts are permission-scoped. Workload is a count, not capacity/utilization. Project status is free text; date variance is a review signal, not a composite risk score. |
| `MarketAnalysisDashboardData` | Competitor/offer inventory, free-text buckets, freshness, and qualitative comparisons. | No market-share, audience-demand, trend, or opportunity score is calculated. Offer price has no currency field. |

## Required catalogue contract

Before configuring a KPI, the accountable domain owner should approve a record containing:

- Stable KPI key, display name, purpose, intended decision, domain owner, and technical maintainer.
- Business definition and calculation formula, including numerator/denominator, aggregation method, rounding, units, and currency treatment.
- Authoritative source models/fields and record identity/deduplication rules; inclusion/exclusion filters and lifecycle states.
- Event date versus record creation date, reporting timezone, inclusive/exclusive boundaries, period/grain, and comparison baseline.
- Missing, unknown, stale, invalid, corrected, soft-deleted, and zero-denominator behavior. Missing must not silently become zero.
- Permission requirements for the metric and each drill-down; allowed dimensions and filters; sensitivity and export rules.
- Freshness target, refresh/cache behavior, provenance/version, reconciliation fixture, expected result, and performance budget.
- Effective date, approval record, versioning/retirement behavior, and whether historical results are recomputed or remain pinned to the original definition.

## Configuration safety requirements

The future configuration layer should use a typed calculation registry or a tightly validated expression language. It must not evaluate arbitrary PHP, SQL, or user-provided executable code. Supported dimensions and source fields should be allow-listed. Authorization must be enforced in the service layer, not only in the Filament interface. Every result should identify the definition version, period, freshness/as-of time, and incomplete-data state.

Thresholds and alerts are separate policy decisions. Do not activate alerts until the owner has approved thresholds, severity, deduplication/cooldown, acknowledgement, escalation owner, stale-data suppression, and notification permissions.

## Recommended sequence

1. Domain owners approve the initial KPI catalogue and reconcile conflicting definitions.
2. For each KPI, add a deterministic calculation service and synthetic expected-result fixtures before making it configurable.
3. Implement versioned definitions and permission enforcement using only approved calculation types.
4. Add aggregation with bounded query cost, explicit freshness/missing states, and source drill-down.
5. Integrate into the existing executive overview only after values reconcile to the calculation service and source records.
6. Add variance alerts only after threshold and operational ownership decisions are approved.

## Validation cases

Cover current/previous period boundaries and timezone changes; empty and zero-denominator inputs; duplicate source records; soft-deleted and corrected rows; missing currency or mismatched currencies; stale inputs; restricted users and record-level visibility; definition version changes; invalid formula/configuration; query cost limits; and reconciliation to independently calculated fixture values.

## Current decision status

The repository documents several existing metric formulas and important limitations, but no single approved cross-domain KPI catalogue or owner-approved policy for configuring arbitrary KPIs was identified. Finance, HR/Operations, and relevant domain owners must approve the initial definitions before KPI configuration, aggregation, or alerting is implemented. This audit intentionally does not assign universal business meaning to current dashboard cards.
