# Dashboard and overview conventions

The Overview Dashboard is the entry point for the dashboard work tracked by issue #97. It links to implemented overview pages only when the current user passes that page's own access check. The landing page never grants access to a destination.

## Shared conventions

- Put each domain overview in the Filament `Overview` navigation group and use its existing page-level permission gate.
- Show the intended purpose and accountable domain on the landing card. Domain owners below are proposed functional owners; a named business owner still needs confirmation.
- Do not display a metric until its source, formula, date basis/timezone, filters, and empty-data behavior are defined.
- Scope every query to the authenticated user's permitted records. Any click-through must use the destination resource's authorization.
- Use explicit empty states and distinguish zero from unavailable/incomplete data.
- Use aggregate queries or precomputed data for summary metrics; avoid per-row queries and N+1 access.
- Cover calculations, date boundaries, empty/incomplete data, and role visibility in automated tests.

## Child overview scope and proposed owner

| Child issue | Purpose and initial scope | Proposed functional owner | Status |
| --- | --- | --- | --- |
| #102 General Overview | Employee presence, average course margin, average feedback, and weekly schedule. Define presence states, margin formula/cost inclusions, feedback scale, timezone, and date windows before displaying values. | Operations | Existing page entry point; metric implementation tracked separately. |
| #105 Financial Overview | Revenue, costs, margin, receivables/payables, and period comparisons where source data supports them. Document formula, currency, and accounting/date basis for every metric. | Finance | Existing page entry point; metric implementation tracked separately. |
| #106 Customer Dashboard | Customer/lead status and recent activity. Prevent double-counting leads and customers and link only to authorized records. | Customer Management | Page entry point not yet implemented. |
| #107 Tasks & Projects | Active work, project phases, due dates, ownership, and overdue items. Confirm schema relationships and status/timezone semantics first. | Project Management | Existing page entry point; metric implementation tracked separately. |
| #108 Products Overview | Product catalog, productable types, prices, availability, and lifecycle. Coordinate with product-price consolidation and product creation observer; show missing/ambiguous prices safely. | Product Management | Page entry point not yet implemented. |

The functional owners are proposals based on the domain names, not confirmed individual assignments. Product and Customer overview pages remain planned and are not linked to nonexistent routes. Detailed metric choices and implementation belong to the corresponding child issues so this parent issue does not invent business definitions.


## Admin landing dashboard (#236)

The admin landing page uses `App\Services\AdminOverviewDashboardData` for the system-level summary, attention queue, and recent activity. It is not a replacement for domain-specific dashboards.

- Student and employee totals count non-soft-deleted records. The current schemas do not provide a reliable active/inactive lifecycle field, so the dashboard does not label these records active.
- Customer totals count non-soft-deleted customers. Open leads are non-soft-deleted lead records whose stored status is neither `converted` nor `lost`.
- Project totals count non-soft-deleted projects. Project status is free text, so the landing page does not infer an active-project count.
- Open tasks are non-soft-deleted tasks whose status is not exactly `done`. Unless `task.view_all` is granted, counts and overdue items are scoped to tasks assigned to or created by the current user, matching the task resource's visibility rule.
- Article, newsletter, and paid-campaign records are kept as separate totals because their lifecycle statuses are not equivalent. Each metric is shown only when the viewer has the corresponding resource list permission.
- Overdue tasks use `due_date` strictly before the current date in `config('app.timezone')`; completed tasks are excluded. The list is capped at eight rows.
- Recent activity displays only the description, event metadata, subject type, and timestamp for activity entries whose subject type corresponds to a resource the viewer may list. Changed attributes and properties are not selected. Activity is omitted when the viewer has no supported resource list permissions.
- Domain overview links continue to require the destination page's own `canAccess()` check. Resource links are emitted only when the matching resource list permission is granted.

This landing page intentionally omits revenue, performance, attendance, teacher capacity, campaign engagement, and other metrics that lack a verified cross-domain definition or permission-safe source here. Domain owners remain responsible for the detailed metric definitions in their respective overview issues.

## Academic Students overview (#237)

`App\Services\StudentsDashboardData` supplies the existing `StudentsDashboard` page. All student counts, enrollment data, and recent additions are available only when the viewer has `student.index`; the page-level `students-dashboard.view` gate does not grant access to student records. The create action additionally requires `student.create`.

- **Total student records** counts all rows, including soft-deleted rows. **Current records** excludes soft-deleted rows, and **Soft-deleted records** counts them separately. None of these labels implies an active/inactive lifecycle because no such status column exists.
- **Enrollment trend** supports 7, 30, and 90 calendar days, including the current application-timezone date. Its start boundary is midnight on the first selected date and its end boundary is midnight after today (exclusive). Daily buckets include zeroes. It counts rows by `created_at`, including rows subsequently soft-deleted, because it represents record creation history rather than current population.
- **Recent student additions** is capped at five non-deleted records created within the same selected date window. It is shown only to viewers with `student.index`.
- **Follow-up required** lists up to eight overdue, unfinished tasks explicitly linked to non-deleted students. It requires both `student.index` and `task.index`; unless `task.view_all` is granted, the task list is limited to tasks assigned to or created by the viewer, matching the task resource scope.
- The student schema has no explicit lifecycle, cohort, program, level, or location field. `gender` is free text rather than a normalized category, so those breakdowns are omitted. Attendance, assessment, progress, and academic-risk queues are also omitted because no reliable signal is defined here.
- No student metrics are shown as zero when `student.index` is absent; the page instead renders an unavailable state. A genuine empty database shows zero population and empty trend/recent/follow-up states.

## Academic Teachers overview (#238)

`App\Services\TeachersDashboardData` supplies the existing `TeachersDashboard` page. The app has no separate Teacher model: teachers are employee records whose linked job description has the exact title `Teacher`, matching the existing employee leave forms. Employee metrics and teacher names require `employee.index`; the page-level `teachers-dashboard.view` gate does not grant resource access.

- **Total teacher records** includes soft-deleted employees; **Current teacher records** and **Soft-deleted teacher records** are reported separately. The employee and group schemas do not define an active/inactive status, so these labels are not used.
- **Current group records** counts non-deleted groups and is available only with `group.index`. **Groups not linked to a current Teacher-role employee** is shown only when both `group.index` and `employee.index` are granted; it includes groups whose teacher relationship is missing, soft-deleted, or linked to an employee whose job title is not exactly `Teacher`.
- Session metrics are available only with `session.index`, `group_session.index`, or `individual_session.index`. If only legacy type-specific permissions are present, counts and trend are restricted to the permitted session type(s). Session counts include non-deleted records with status exactly `scheduled` and `starts_at` inside the selected 7/30/90 calendar-day range. The first date is inclusive at midnight; midnight after today is exclusive; timezone is `config('app.timezone')`.
- **Sessions with a teacher ID** and **Sessions with no teacher ID** are counted directly from the `teacher_id` column. With `employee.index`, **Sessions without a current Teacher-role employee** additionally identifies scheduled sessions linked to a missing, soft-deleted, or non-Teacher employee record.
- The daily teaching activity trend counts sessions with the exact status `scheduled`. Session status is free text; completed-session totals are omitted rather than inferred from arbitrary strings.
- Teacher workload lists up to 12 current Teacher-role employees, ordered by scheduled-session count and then current group assignments. Session counts use the selected period; group assignment counts use all current non-deleted groups and are not date-filtered. The no-assignment count is limited to the assignment sources the viewer can access.
- Workload values are counts, not hours or utilization. The schema does not provide a reliable capacity denominator, and no utilization percentage is shown. Quick links require their destination's own employee, group, or session list/create permission.

## Marketing Content Management overview (#239)

`App\Services\ContentManagementDashboardData` supplies the existing `ContentManagement` page. Primary inventory types are articles, newsletters, social posts, and paid campaigns; content templates are shown separately as supporting assets. Product reviews and competition entries appear in a separate moderation queue because they are public submissions, not editorial inventory.

- Each content type has its own current-record count, soft-deleted count, and raw status distribution. Status buckets are grouped by exact stored value and only count non-deleted records. Status strings are free text, so no shared draft/scheduled/published/failed lifecycle is inferred across types. Content templates have no status field.
- Upcoming lists use the type-specific date field: `scheduled_on` for articles, newsletters, and social posts; `scheduled_to_start_on` for paid campaigns. The date-only window is today through 29 days after today inclusive (30 calendar dates), based on `config('app.timezone')`. The stored status is displayed unchanged; a future date alone does not mean a record is queued for publication.
- Recent publication lists are available only for current articles and social posts with status exactly `published` and `published_on` within the past 30 calendar dates, including today. Newsletter `delivered_on` and paid-campaign start/end dates are not treated as publication events because those types do not have a controlled, consistent publication status.
- The review queue counts only current product reviews and competition entries whose status is exactly `pending_review`. The two queues remain separate. Each count and link requires the matching resource list permission.
- The content-management page gate does not grant access to content records. Each inventory/status query and list link requires its resource's `*.index` permission; create links additionally require the matching `*.create` permission. Without list permission, the dashboard shows an unavailable state rather than a zero count.
- Reach, clicks, opens, reactions, and engagement rates are not stored in these content schemas and are intentionally not estimated.
