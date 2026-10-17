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
