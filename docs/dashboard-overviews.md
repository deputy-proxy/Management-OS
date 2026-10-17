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
