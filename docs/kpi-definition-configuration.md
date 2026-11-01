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

The catalogue registry currently supports daily as-of periods and no dimensions. It rejects duplicate keys, unsupported dimensions, and unsupported period contracts. A missing source model or calculation handler makes the KPI explicitly unavailable; it does not produce a fabricated zero. Final business definitions and real-source reconciliation are deferred.
