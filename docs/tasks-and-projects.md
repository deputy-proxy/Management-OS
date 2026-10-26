# Tasks & Projects Overview

## Relationship findings

- `tasks.project_id` references `projects.id`; `Task::project()` belongs to `Project`.
- `tasks.project_phase_id` references `project_phases.id`; `Task::projectPhase()` belongs to `ProjectPhase`.
- `project_phases.project_id` references `projects.id`; `ProjectPhase::project()` belongs to `Project`.
- `tasks.taskable_type` / `taskable_id` are a separate optional polymorphic relationship to the record that originated the task. They do not replace the explicit project and phase links.
- The original `Task` relationships pointed to the opposite models, and the phase factory created a `Process` rather than a `Project`. These were corrected so forms, eager-loaded dashboard records, and factories agree with the schema.

## Metric definitions

| Metric | Definition |
| --- | --- |
| Visible tasks | Non-soft-deleted tasks visible under the existing task-resource scope. Users with `task.view_all` see all tasks; otherwise they see tasks assigned to them or created by them. The `task.index` permission is required to show task metrics. |
| Open tasks | Visible tasks whose status is not exactly `done`. |
| Task status | Counts grouped by stored status; the task form defines `pending`, `in_progress`, and `done`. |
| Overdue tasks | Open tasks with `due_date` strictly before today in the application timezone. Due today is not overdue. |
| Due in next 7 days | Open tasks with due date from today through today + 7 days, inclusive, sorted by due date then ID. |
| Projects in scheduled date range | Non-soft-deleted projects where `start_date <= today <= end_date`. |
| Phases in scheduled date range | Non-soft-deleted phases where `start_on <= today <= end_on`. |

## Authorization

The page has a dedicated `tasks-and-projects.view` permission. Task metrics are shown only when the viewer has `task.index`; aggregate and list queries follow the task resource's own-vs-all scope. Project and phase metrics are independently hidden unless `project.index` or `project_phase.index` is granted. Links to each resource are emitted only with its matching list permission. The dashboard permission does not grant access to the underlying records.

## Limitations and assumptions

- Project `status` is a free-text field with no controlled values in the form/schema. The dashboard therefore labels a project as being in its scheduled date range based on dates, rather than assuming that a status string such as `active` has a canonical meaning.
- The same applies to project-phase lifecycle state: the schema has dates and order but no status field.
- Tasks are completed only when their status is exactly `done`, matching the task form's defined options. Unknown/legacy statuses are still displayed in status counts and considered open until normalized.
- Task lists support status, assignee, project, and due-window filters. The summary/status inventory remains based on the visible task scope so available filter choices do not disappear when a filter is selected.
- Dashboard lists show at most eight overdue tasks and eight upcoming tasks. Full authorized lists remain available through the resource links.
- The current schema uses date columns for due/start/end dates, so comparisons use application-local calendar dates rather than interpreting these as timestamps.

## Delivery attention signals

The overview adds three explainable counts, each linked back to existing records and governed by the page's existing permission checks:

- **Overdue open tasks:** task status is not exactly `done` and `due_date` is strictly before today in the application timezone.
- **Projects started after plan:** recorded `start_date` is later than `planned_start_date`.
- **Projects finished after plan:** recorded `end_date` is later than `planned_end_date`.

Project date comparisons use the stored fields directly and do not infer status from the free-text project status. They are schedule-variance signals for review, not proof of current delivery failure; validate that dates are populated accurately and that project teams use the fields consistently. The current schema requires task assignee and due date, so missing-owner and missing-due-date counts would be misleading without schema/data evidence that such records can exist.

These are review prompts, not a composite risk score. Reviewers should open source records, confirm dates/status, and agree corrective action with the owner. Structured task dependencies are now available through prerequisite links, but blocked reasons, meaningful-update timestamps, and business impact are not available as dedicated task fields. The report therefore does not claim to detect blocked work, stale work, or business impact. No alert thresholds, automated notifications, or production-monitoring readiness are implied by these dashboard counts.


## Task dependencies and workload visibility

Tasks may declare prerequisite tasks using the existing task form. The dependency direction is `task_id` depends on `depends_on_task_id`; the inverse relation lists tasks that depend on a prerequisite. Duplicate edges are rejected by a unique database constraint, task deletion cascades its edges, and form validation rejects self-dependencies and proposed edges that would create a cycle. Dependency validation is application-level and should not be treated as a concurrency-proof graph constraint; concurrent edits should be reviewed if dependency editing becomes a high-contention workflow.

The task list displays prerequisite titles and a derived dependency-readiness label. A task with no prerequisites is labelled `No prerequisites`; a task with one or more prerequisites whose status is not exactly `done` is labelled `Blocked (N)`, where N is the number of incomplete prerequisites; otherwise it is labelled `Ready`. For blocked tasks, the label description names each incomplete prerequisite and its current status. These details are derived from current records, not persisted task status or policy decisions. They do not automatically prevent status changes or send notifications. The task resource eager-loads the prerequisite fields used by this label.

The overview also shows the top ten assignees by visible open-task count and their overdue counts, using the existing task visibility scope. This is workload visibility only: the application has no effort estimates, contracted hours, availability calendar, or approved overload thresholds, so the report does not label a person overloaded or infer capacity/utilization. Overdue tasks remain the current escalation signal; no automated notification, escalation ownership, or deduplication policy is introduced here.
