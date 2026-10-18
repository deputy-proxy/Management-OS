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