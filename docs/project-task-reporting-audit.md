# Project and task reporting audit

**Status: source-backed audit of the current implementation.** This document describes the code and tests inspected for issue #382. It does not claim that project data is complete, that operational dates are maintained accurately, or that the reporting is production-verified.

## Scope and inspected sources

Inspected:
- `app/Models/Project.php`, `ProjectPhase.php`, and `Task.php`
- `app/Services/TasksAndProjectsData.php`
- `app/Filament/Pages/TasksAndProjects.php`
- task, project and project-phase resource forms and tables
- `tests/Feature/TasksAndProjectsDataTest.php`
- `docs/tasks-and-projects.md`

The existing project/task workflow is reused. This audit does not propose a second task system.

## Current source data and reporting

| Area | Source fields / behavior | Current reporting use | Gap / limitation |
| --- | --- | --- | --- |
| Project identity | `Project.title`, `description`, `project_manager`, `status`, `planned_start_date`, `planned_end_date`, `start_date`, `end_date`, `budget`; soft deletes and activity logging | Total projects and count where `start_date <= today <= end_date` | `status` is free text. Date-range membership is not proof that work is active, healthy, or on schedule. Planned and actual date meanings are not reconciled by this dashboard. |
| Project phases | `project_id`, `title`, `description`, `start_on`, `end_on`, `order`; soft deletes and activity logging | Total phases and count where `start_on <= today <= end_on` | No phase status, completion date, dependency, or progress field is used by this report. Scheduled range is not completion or risk. |
| Tasks | `action`, `description`, `assigned_to`, `project_id`, `project_phase_id`, `status`, `due_date`, optional polymorphic `taskable_type/id`, `created_by`; soft deletes and activity logging | Total, open, overdue, status counts, overdue/upcoming lists and filter options | Status options are `pending`, `in_progress`, and `done`; unknown legacy values are counted and are treated as open unless exactly `done`. No start/completion timestamp or blocked reason is represented in the inspected task fields. |
| Task ownership and access | Assigned user and creator; `task.view_all`, `task.index`, project and phase list permissions | Own/created task scope for users without view-all permission; project/phase metrics and links separately permission-gated | Reports reflect the viewer's permitted scope, not necessarily organization-wide totals. Aggregates should not be compared across viewers without stating scope. |
| Relationships | Task has explicit project and phase foreign-key relationships; optional polymorphic taskable relation remains separate; phase belongs to project | Task list can show project/phase context | A task's optional origin relation does not substitute for its project/phase assignment. The inspected report does not validate phase belongs to the same project as the task. |
| Audit history | Models use activity logging for selected fields | Changes to selected project/task fields can be logged | This audit did not verify retention, completeness, alerting, or production access to activity logs. Activity logging is not proof of an operational review process. |

## Metric definitions and edge behavior

- **As-of date:** application-timezone calendar date from the injected/current `CarbonImmutable` timestamp.
- **Open task:** task status is not exactly `done`.
- **Overdue task:** open task with `due_date < today`. Due today is not overdue.
- **Upcoming task:** open task with due date from today through today plus seven days, inclusive. Results are ordered by due date then ID and capped at eight rows.
- **Overdue list:** ordered by due date then ID and capped at eight rows.
- **Project in date range:** non-soft-deleted project whose `start_date` and `end_date` inclusively contain today. It is not derived from project status or planned dates.
- **Phase in date range:** non-soft-deleted phase whose `start_on` and `end_on` inclusively contain today.
- **Status inventory:** grouped by the stored status string. Unknown/legacy status values remain visible as separate groups.
- **Visibility:** task totals, lists, status counts and filter options use the existing task scope. Project and phase counts are independently permission-gated.

## Existing test evidence

`tests/Feature/TasksAndProjectsDataTest.php` currently covers:
- overdue and upcoming date boundaries, including due today and the inclusive seven-day endpoint;
- completed overdue tasks excluded from open/overdue results;
- soft-deleted tasks excluded;
- status counts and project/phase date-range counts;
- status, owner, project and due-window filters;
- assigned-or-created task visibility when view-all is not granted;
- hidden metrics when the viewer lacks the relevant permissions;
- task/project/phase relationships matching foreign-key columns.

These tests provide automated coverage of the tested service behavior. They do not prove real project dates, task statuses, ownership assignments, permissions in every UI path, production monitoring, or data completeness are correct.

## Delivery-risk signals available versus missing

| Signal | Current evidence | Safe interpretation |
| --- | --- | --- |
| Late work | Task due date plus status | Can report overdue tasks if source dates/statuses are maintained. |
| Near-term work | Task due date plus status | Can report tasks due in the next seven calendar days. |
| Ownership | Assigned user and creator | Can identify assigned/created-by scope; not actual capacity or accountable project owner for every task. |
| Project/phase schedule | Planned and actual project dates; phase dates | Dates can be compared after a separately defined schedule variance rule. The current dashboard does not calculate variance. |
| Blocked work | No blocked status/reason identified in inspected task form/model | Not currently reportable as a reliable dedicated signal. Do not infer blocked state from arbitrary descriptions or unknown status strings. |
| Dependencies | No task dependency relationship identified in inspected models/service | Dependency risk is not currently reportable from this implementation. |
| Progress/completion | Task status only; no completion timestamp identified in inspected fields | Status counts are not a validated percentage-complete measure. |
| Staleness | No last-meaningful-update freshness rule in the report | Stale records cannot currently be distinguished reliably. Activity logs may support future analysis, subject to a defined policy and access review. |
| Impact | Project budget and project linkage exist | The current report does not map task risk to approved financial, customer, milestone, or delivery impact. Budget alone is not a validated impact measure. |

## Targeted next steps

1. Agree an operational owner for project/task data quality and define the expected cadence for updating dates, status and assignment.
2. Define project status vocabulary and whether planned/actual dates have consistent semantics. Migrate/normalize legacy values only with a reviewed mapping.
3. If blocked work is needed, add an explicit approved status or blocked flag/reason with permissions, activity logging and transition tests. Do not overload free-text descriptions.
4. If dependency risk is needed, model explicit task dependencies with cycle prevention, deletion behavior and authorization before computing dependency-based risk.
5. Define schedule variance and stale-input rules, including date/timezone semantics, missing-date behavior, thresholds, source drill-down and next-action ownership.
6. Extend synthetic fixtures and tests for each approved signal, unknown statuses, missing dates, soft deletion, permission scope, project/phase mismatch and deterministic sorting.
7. Validate a sample of real project/task records with the operational owner before publishing organization-wide risk metrics.

## Non-goals and release boundary

- No new task-management system.
- No opaque composite risk score without explainable contributing signals.
- No claim that a date-range count equals project health or delivery confidence.
- No organization-wide totals where the user's permissions intentionally scope the data.
- No production-readiness claim based only on passing unit/feature tests.

**Current decision:** existing task due-date reporting can support basic overdue/upcoming visibility. Blocked-work, dependency, staleness, schedule-variance and impact signals require additional approved definitions and, in several cases, source data not identified in the inspected implementation.
