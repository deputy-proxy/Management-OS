# KPI definition configuration and versioning policy

**Policy version:** `v1`

**Status:** Provisional; no executive/domain-owner approval is recorded.

**Catalogue:** `config/kpi_catalogue.php`

## Definition lifecycle

1. An authorized KPI editor creates a draft by selecting a stable key from the registered provisional catalogue and entering an effective date plus optional target/warning/critical values.
2. The application copies the key's label, description, domain, calculation type, calculation service, source service, formula, unit, dimensions, supported period, date basis, permission, and missing-data policy from the catalogue. These semantic fields are not free-form.
3. The system assigns the next version number for that key. Drafts can be edited; drafts do not affect dashboard calculations.
4. A user with `kpi_definition.approve` activates the draft in the application. Activation records the approver and timestamp and closes any overlapping earlier version the day before the new effective date. This is technical workflow approval, not executive/business-owner approval of the KPI meaning. Activation cannot be backdated.
5. Activated semantic fields are immutable. A changed target, threshold, effective period, or meaning requires a new version or an authorized retirement action. Definitions are never deleted.
6. Retiring an active definition records actor/time and stops it being selected for future dashboard calculations. Prior rows remain for audit and historical interpretation.

## Safe configuration contract

- Only keys and calculation types in `config/kpi_catalogue.php` are supported. Arbitrary formulas, SQL, PHP, and dynamic property paths are not accepted.
- Threshold direction must be `higher_is_worse`, `lower_is_worse`, or `target_only`. Negative targets/thresholds are rejected. For higher-is-worse metrics, warning must not exceed critical; for lower-is-worse metrics, warning must not be below critical.
- Thresholds annotate a KPI result; they do not send alerts or trigger escalations. Alert cooldown, owner, acknowledgement, delivery, and action policies remain separate.
- The aggregation service applies the catalogue permission before querying. Missing permission yields `unavailable_permission_denied` with a null value, never zero.
- Task calculations use the viewer's task scope unless `task.view_all` is granted. Due-today tasks are not overdue.
- Active definitions are selected by effective date in the configured reporting timezone. A new version does not silently rewrite the effective period or meaning of a prior version.
- Static catalogue definitions remain the fallback when no database definition is active for a key. The v1 catalogue is a provisional technical baseline, not a business-approved KPI policy.

## Operational controls

KPI definition list, draft creation, editing, approval, and retirement use separate gates: `kpi_definition.index`, `kpi_definition.create`, `kpi_definition.update`, and `kpi_definition.approve`. No delete action is provided. Tests cover allow-listed keys, version increments, effective-date handoff, immutability, permission-gated aggregation, and threshold evaluation.

The catalogue registry supports daily as-of periods. Versioned lead-count definitions additionally support the registered status and source-category dimensions; other calculation families currently expose no configurable dimensions. The configuration service rejects duplicate or unsupported dimensions, unsupported fields/operators/values, excessive filter or dimension counts, and unsupported period contracts. A missing source model or calculation handler makes the KPI explicitly unavailable; it does not produce a fabricated zero. Final business definitions and real-source reconciliation are deferred.


## Configurable draft settings

Drafts persist filter, dimension, aggregation, and period settings alongside the versioned definition. The v1 configuration service currently permits only registered lead fields (status and source_category) for lead-count calculations, with controlled values from the lead model. Filters are capped at five, dimensions at two, and synthetic preview input at 100 rows. Only the registered count aggregation and daily as-of period are accepted; unsupported operators, fields, values, and historical/range periods fail validation.

Synthetic preview runs against caller-supplied sample rows only. Its response is explicitly marked synthetic; it does not query production records or claim to forecast live KPI values. Live lead-count aggregations apply the validated filters. Live lead-count aggregations also return grouped breakdowns for the selected registered dimensions, capped at 50 groups. Other calculation families remain unconfigurable until their source-specific field contracts are registered.


## Immutable audit history

Every draft creation, draft update, activation, superseding effective-date change, and retirement performed through the KPI definition service writes an append-only audit row. Each row records the actor, event timestamp, event type, effective definition version, a human-readable change summary, and structured before/after values where applicable. The Filament create/edit workflow requires a change summary and routes writes through the authorization-enforcing service. Direct service calls enforce the create, update, and approve gates independently. Audit rows are retained with their definition and are not user-editable. Aggregation results include the selected definition version so reports can identify the configuration used.


## Deterministic aggregation result contract

Each available aggregation returns the exact included count and the candidate count before KPI filters or the overdue cutoff. The excluded count is their difference. Candidate queries retain the same model scopes and viewer visibility rules as the included query, so excluded counts do not reveal records outside the caller's permitted scope. Missing viewer context for task-scoped calculations returns an unavailable state with null counts rather than zero.

Results identify the daily as-of date, inclusive calendar-date boundary, and reporting timezone. This is a current-state snapshot labelled with an as-of date; it is not a historical reconstruction of what source records looked like on that date. Historical periods remain unsupported.

Freshness metadata records when the aggregation was evaluated. Source update timestamps and source staleness are null because the registered sources do not currently expose a consistent freshness contract. The service does not claim that evaluation time proves source freshness. Caching is intentionally not enabled until source-specific invalidation rules exist.

Provenance identifies the source model, calculation type/service, formula, filters, definition version, and up to 100 ordered source record IDs. Exact aggregate counts are not truncated. Dimension breakdowns are capped at 50 groups. Both limits are explicit in the response. Source record IDs are emitted only after the KPI permission and task visibility scope have been applied.

All current v1 calculations are record/snapshot counts. No currency conversion, financial aggregation, inferred zero, or historical correction replay is performed. A source correction is reflected on the next uncached evaluation of current state; it does not rewrite a stored historical result because historical result storage is not part of this increment.

## Threshold alerts

KPI definition drafts may enable a provisional threshold alert rule by setting a warning/critical threshold, a higher-is-worse or lower-is-worse direction, an explicit owner, and a cooldown from 1 minute to 7 days. Alert settings are part of the immutable effective-dated KPI definition version. Rules are disabled by default; target-only definitions cannot emit alerts.

The `kpi:alerts-evaluate` command runs every five minutes. It evaluates each enabled definition as its configured owner and uses the shared permission-scoped KPI aggregation service. An incident is deduplicated by KPI key and definition version. Repeated observations update the open incident; severity changes and lifecycle actions are recorded in append-only `kpi_alert_events`. A normal fresh result resolves an open/acknowledged incident and starts the configured cooldown before a new incident may be opened. Owners with `kpi_alert.manage_own` may acknowledge or resolve their incidents; `kpi_alert.manage` grants global management. `kpi_alert.view_own` and `kpi_alert.index` scope alert visibility.

Alerts are suppressed when the KPI result is unavailable, the value is missing, freshness is unknown, or the source is stale. Suppressed observations do not resolve an existing incident because they cannot prove recovery. The current aggregation contract reports `source_freshness_unknown` for its registered sources, so scheduled evaluation deliberately does not raise notifications until source freshness can be established. This is a safety constraint, not a green state.

Notification delivery is disabled by default (`KPI_ALERT_DELIVERY_ENABLED=false`). The delivery adapter is replaceable; the included adapter writes an in-app Filament notification only when delivery is explicitly enabled and the configured owner has `kpi_alert.notify`. No email or external provider is configured. Threshold values remain provisional and production delivery must not be enabled until thresholds, channels, and named owners are approved.
