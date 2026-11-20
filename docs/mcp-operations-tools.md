# MCP operations tools

**Issue:** #536
**Release:** 2, read-only management intelligence
**Permission matrix:** 1.4.0

The operations registrar exposes eight read-only tools. Each tool is registered through AuthorizedMcpCapabilityRegistrar and uses current HexaLite permissions plus the record scope declared in config/mcp.php. Lists and searches apply record visibility before filters, counts and bounded database pagination.

## Tool catalogue

| Tool | Required domain permissions | Optional permissions | Inputs |
| --- | --- | --- | --- |
| get_projects | project.index | — | status, project_manager_id, planned_start_from/to, sort_by/direction, limit, offset |
| get_project_details | project.index | project_phase.index | project_id |
| get_tasks | task.index | task.view_all | status, assigned_to, project_id, due_date_from/to, sort_by/direction, limit, offset |
| get_task_details | task.index | task.view_all | task_id |
| get_overdue_tasks | task.index | task.view_all | as_of, assigned_to, project_id, limit, offset |
| get_operational_bottlenecks | task.index | task.view_all, project.index, project_phase.index | No arguments |
| get_quarterly_objectives | quarterly_objective.index | — | quarter, status, sort_by/direction, limit, offset |
| get_meetings | meeting.index | — | query, type, status, from_date/to_date, sort_by/direction, limit, offset |

All schemas reject additional properties. Page size is bounded to 100 rows and offset to 1,000. Sort columns are fixed allowlists. Filters are applied after the declared record scope. The three task-list/detail tools use McpTaskRecordScope: without task.view_all, the principal sees only tasks assigned to or created by that principal.

## Output and privacy contract

- Project tools return title, status, planned/actual dates and project-manager name. Budget amounts, descriptions and internal project notes are excluded. Project phases are returned only when project_phase.index is also granted.
- Task tools return action, status, due date, estimated effort, assignee name and related project/phase titles. Free-text descriptions, creator identity, taskable IDs and dependency records are excluded.
- Operational bottlenecks reuse TasksAndProjectsData. Its task query is pre-scoped by McpTaskRecordScope before task totals, status counts, overdue counts and assignee workload are aggregated. Project schedule metrics are included only with project.index; phase metrics additionally require project_phase.index. Assignee IDs are not returned.
- Quarterly objectives return objective text, quarter, status, creation timestamp and employee display name. Employee IDs and free-text descriptions are excluded. The dedicated quarterly_objective.index permission is required.
- Meeting results return subject, type, status, start/end, location, organizer display name and participant count. Descriptions and participant identities are excluded.
- No tool creates or updates projects, phases, tasks, objectives or meetings.

## Date, status and reporting semantics

- Date and time values are presented in Europe/Bucharest.
- Overdue tasks require a non-null due date strictly earlier than as_of and status not equal to done. A due date equal to as_of is not overdue. Null due dates are excluded defensively and are never assigned a synthetic date.
- The existing task service defines open tasks as status not equal to done. Unknown status values are returned unchanged and are not normalized or relabelled. They remain visible in raw status counts.
- Operational bottleneck workload counts and project schedule variances reuse TasksAndProjectsData semantics. The service's own date/status rules are not reimplemented with inferred states.
- Project schedule metrics are unavailable rather than zero when project.index is absent. Phase metrics are unavailable when project_phase.index is absent.
- Meeting date filters are inclusive by local calendar date and apply to starts_at.
- Quarterly-objective status and quarter are returned as stored; the MCP layer does not infer a status from objective text or dates.

## Authorization notes

The task record scope mirrors the task resource's own/all visibility rule: task.view_all grants all tasks; otherwise a task must be assigned to or created by the AI principal. The bottleneck aggregate receives the same already-scoped query, preventing aggregate counts from widening the visible record set.

Project, project-phase, meeting and quarterly-objective tools use their respective existing index permissions. Optional permissions only add the related data described above; they do not widen the required tool permission. The AI principal receives no permissions automatically.

See MCP permission matrix, MCP server and MCP integration plan for transport, identity and approval boundaries.
