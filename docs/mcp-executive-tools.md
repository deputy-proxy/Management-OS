# MCP executive and management tools

Issue #533 adds six read-only executive capabilities. Every tool uses the shared MCP authorization, audit, and output-filtering boundary. The handlers call existing dashboard, KPI, alert, and task/project services; they do not introduce parallel business calculations.

## Permission model

All overview, KPI, business-health, priorities, and period-comparison calls require the existing `overview-dashboard.view` permission and their dedicated OAuth tool scope.

The executive tools also declare optional domain permissions. These permissions are allow-listed for the dedicated Assistant role but are not required to invoke the tool. Each source is included only when the AI principal currently has that exact domain permission:

- `student.index`, `employee.index`, `customer.index`, `customer_lead.index`
- `competitor_offer.index`, `project.index`, `project_phase.index`, `task.index`, `task.view_all`
- `article.index`, `newsletter.index`, `paid_campaign.index`

This is deliberate: an overview permission does not grant access to the underlying resource. Task queries retain the existing assigned-to/created-by scope unless `task.view_all` is explicitly granted. Counts and aggregates are calculated only from the visible source records.

`get_kpi_alerts` is separately gated by `kpi_alert.view_own` and uses the owner record scope. It returns only alerts visible to the AI principal. It does not expose alert owners, lifecycle event metadata, or raw evidence payloads.

The permission matrix version is `1.1.0`. Optional permissions are included in the provisioning allowlist, but are checked individually by the executive handlers before a source is queried.

## Tool contracts

| Tool | Inputs | Source and behavior |
| --- | --- | --- |
| `get_executive_overview` | Empty object | Reuses `AdminOverviewDashboardData::summary`; returns authorized inventory metrics, provisional KPI results, limited overdue task details, and safe recent-activity metadata. |
| `get_management_kpis` | Empty object | Reuses `KpiAggregationService::current`; returns the registered KPI catalogue with units, periods, source mapping, formula, freshness state, and unavailable reasons. |
| `get_kpi_alerts` | Optional `status`, `severity`, `limit` (1–50; default 20) | Reuses `KpiAlertService::queryForCurrentUser`; owner-scoped and sorted by most recently seen. |
| `get_business_health` | Empty object | Returns available KPI signals and catalogue approval state. No composite score is calculated. |
| `get_management_priorities` | Optional `limit` (1–20; default 8) | Reuses `TasksAndProjectsData::summary`; returns visible overdue and next-seven-day tasks ordered by due date. No priority score is inferred. |
| `get_period_comparison` | Required current/comparison start and end dates; optional IANA timezone | Validates both periods, then returns an explicit unsupported state because KPI catalogue v1 does not support historical reconstruction or date-range aggregation. |

All outputs include reporting context where relevant. Executive overview, KPI, business-health, priorities, and comparison reporting context use the configured KPI reporting timezone. Individual activity and alert timestamps retain their serialized source timezone offsets. Tool result lists are bounded.

## Output fields

The shared output filter rejects fields not named below, including nested fields not explicitly listed in the runtime allowlist.

- `get_executive_overview`: `status`, `as_of`, `timezone`, `metrics[]` (`label`, `value`, `definition`), `kpis` (catalogue status/version and `results[]`), `overdue_tasks[]` (`action`, `due_date`, permission-gated `project`), `recent_activity[]` (`event`, `subject_type`, `created_at`), and `activity_available`.
- `get_management_kpis`: `status`, `catalogue_version`, `catalogue_approval_status`, `as_of`, `timezone`, and `results[]`. Each result includes the registered KPI key/label/domain/unit/value/state, availability, period, freshness, source/provenance, formula, missing-data reason, approval status, thresholds, and definition.
- `get_kpi_alerts`: `status`, `returned_count`, `total_visible_count`, `has_more`, and `alerts[]` containing severity/status, observed value/threshold, lifecycle timestamps, KPI key/label, as-of timestamp, and source. Raw evidence and owner fields are excluded.
- `get_business_health`: `assessment_status`, `reason`, catalogue version/approval status, `as_of`, `timezone`, and `signals[]` containing only authorized KPI values and their availability/threshold states.
- `get_management_priorities`: `availability`, optional `reason`, `as_of`, `timezone`, `priority_basis`, `overdue_count`, `overdue_tasks[]`, and `upcoming_tasks[]`. Project and phase names are independently permission-gated.
- `get_period_comparison`: `availability`, `reason`, `catalogue_version`, `supported_period`, the validated current/comparison periods, `timezone`, and an empty `results[]` while historical comparison remains unsupported.

## Data semantics and limitations

- The KPI catalogue is **provisional**. No executive or domain-owner approval or real-source reconciliation is recorded.
- KPI v1 supports daily as-of snapshots only. It does not reconstruct historical values or calculate arbitrary date ranges.
- A missing or unauthorized source returns an unavailable state with a null value, not zero.
- KPI freshness is reported as unknown where the source does not provide a verified update timestamp.
- The application has no approved universal business-health formula. `get_business_health` therefore returns `assessment_status: not_scored`, not an invented score.
- The application does not define a universal task-priority score. `get_management_priorities` uses due-date ordering and labels that basis explicitly.
- Task counts and task lists preserve the assigned-to/created-by visibility rule unless `task.view_all` is granted.
- Recent activity excludes activity properties and descriptions to avoid exposing sensitive free text.
- KPI alert output excludes raw evidence, source record IDs, and owner identity.

## Validation

Focused coverage is in `tests/Feature/McpExecutiveToolsTest.php` and `tests/Feature/McpAuthorizationTest.php`. The tests cover tool discovery, optional domain permissions, source-level permission filtering, task visibility, alert ownership, bounded filters, unavailable historical comparisons, and the no-score business-health contract.
