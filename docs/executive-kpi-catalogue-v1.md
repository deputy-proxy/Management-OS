# Executive KPI catalogue v1

**Version:** `v1`  
**Effective date:** 2026-10-27  
**Reporting timezone:** `KPI_REPORTING_TIMEZONE`, default `Europe/Bucharest`  
**Scope:** Initial operational inventory KPIs with explicit source and permission contracts.

This catalogue is intentionally narrow. It does not turn existing financial, HR, customer-lifecycle, or market dashboard cards into approved business KPIs merely because they already exist. Definitions are allow-listed in `config/kpi_catalogue.php`; calculations must use typed calculation services, not arbitrary PHP, SQL, or user-entered formulas.

## Approved initial definitions

| Key | Calculation | Source | Unit | Semantics / exclusions |
| --- | --- | --- | --- | --- |
| `customer_record_count` | `customer_record_count` | Non-soft-deleted customer records | Records | Not active customers, paying customers, or unique people across merged identities. Requires `customer.index`. |
| `unconverted_lead_count` | `unconverted_lead_count` | Non-soft-deleted leads with no converted-customer link | Records | Stored status does not override the conversion link. Requires `customer_lead.index`. |
| `unknown_lead_source_count` | `unknown_lead_source_count` | Controlled lead source category | Records | `unknown` is explicit and never inferred from legacy source text. Requires `customer_lead.index`. |
| `open_task_count` | `open_task_count` | Tasks whose status is not exactly `done` | Records | Assigned-to/created-by visibility unless `task.view_all` is granted. Requires `task.index`. |
| `overdue_task_count` | `overdue_task_count` | Visible tasks with status not `done` and due date strictly before local reporting date | Records | Due today is not overdue. This is not capacity or severity scoring. Requires `task.index`. |
| `competitor_offer_snapshot_count` | `competitor_offer_snapshot_count` | Append-only competitor offer observations | Snapshots | Includes historical observations for later soft-deleted offers; does not measure market share or source accuracy. Requires `competitor_offer.index`. |

## Shared calculation contract

- The stable key and calculation type are code allow-listed. A definition cannot introduce an unregistered calculation type or source field.
- A result must carry its key, catalogue version, reporting date/timezone, source query scope, result value, freshness/as-of timestamp, and an unavailable/incomplete state where required.
- Authorization is enforced in the service layer as well as the UI. If the required source permission is absent, the result is unavailable, not zero.
- Counts exclude soft-deleted source rows unless the definition explicitly counts immutable history snapshots. Unknown values remain explicit categories; missing data is not silently converted to zero.
- Reporting periods must state whether they use an effective date, event date, or current inventory. Local-date cutoffs use the configured reporting timezone.
- Targets and thresholds are not part of this v1 catalogue. Alert severity, cooldown, acknowledgement, escalation owner, and delivery policy require separate definitions before alerts are enabled.
- Financial totals, margins, payroll values, CAC, CLV, retention revenue, and cross-currency comparisons are excluded pending their own domain-specific definitions and reconciliation fixtures.

## Version and change control

Changes to business meaning require a new catalogue version, updated synthetic expected-result fixtures, source reconciliation, and review by the relevant domain owner. Retired definitions remain available for historical interpretation. Do not rewrite prior result meaning silently when a calculation changes.

## Validation requirements

Tests should assert unique stable keys, allow-listed calculation types, required source/permission metadata, nonempty descriptions, and explicit missing-data behavior. Runtime configuration must reject unsupported keys/types and enforce source permissions before querying data.

## Versioned KPI definition workflow

The initial definitions are supplied by the allow-listed `config/kpi_catalogue.php` registry. The `KpiDefinition` workflow adds effective-dated, versioned operational configuration without allowing a user to introduce executable formulas or arbitrary source fields.

- A draft is created by selecting an existing catalogue key. Its label, description, domain, calculation type, unit, source, date basis, permission and missing-data contract are copied from the allow-list and are not user-editable.
- Drafts may set a target and optional warning/critical thresholds, with an explicit direction (`higher_is_worse`, `lower_is_worse`, or `target_only`). Thresholds are inert when direction is `target_only`; they do not send notifications.
- Only a user with `kpi_definition.approve` may activate or retire a definition. Activation records approver and timestamp. A new effective version closes the previous version on the preceding calendar day; approved calculation semantics cannot be edited in place.
- Definition records cannot be deleted. Historical versions remain available for audit and interpretation. A new version receives a monotonically increasing version number for its stable key.
- Aggregation uses the fixed calculation registry. Source permissions are checked in the service layer; unavailable values remain null with an explicit availability state. Task counts preserve the assigned-to/created-by scope unless `task.view_all` is granted.
- The overview displays the v1 catalogue with the active effective-dated database definition taking precedence for a matching key. Threshold state is informational only; no alert, escalation, or notification delivery is implied.

The supported configuration surface is deliberately narrower than arbitrary KPI authoring. Adding a new calculation requires code, a source contract, permission review, expected-result fixtures, and a new catalogue release.
