# Executive KPI catalogue v1

**Version:** v1
**Approval status:** Provisional; no executive or domain-owner sign-off is recorded.
**Reporting timezone:** KPI_REPORTING_TIMEZONE, default Europe/Bucharest
**Scope:** Operational inventory KPIs with explicit source, calculation, period, dimension, permission, and missing-data contracts.

This is a code-backed technical catalogue, not a business-approved KPI policy. The definitions are provisional examples grounded in existing application behavior. Final business definitions and reconciliation against real source data remain deferred. Existing financial, HR, customer-lifecycle, and market dashboard cards are not automatically approved KPIs.

## Catalogue contract

Each definition in config/kpi_catalogue.php has:

- A stable, unique key, label, description, and domain.
- A unit and explicit list of supported dimensions. The initial catalogue supports no dimensions; unsupported dimensions are rejected.
- A supported period contract. v1 supports daily as-of snapshots only. Historical point-in-time reconstruction and date-range aggregation are not supported.
- An allow-listed calculation type and calculation service.
- A source model/service class and a human-readable source mapping.
- A required permission and explicit missing-data behavior.
- A formula documenting the calculation and its key inclusion/exclusion rules.
- A provisional approval status.

KpiCatalogueRegistry validates records, rejects duplicate keys and invalid dimensions/period contracts, and marks definitions unavailable when their source model or calculation handler is missing. KpiAggregationService consumes the registry and returns the definition metadata alongside the result, so dashboard and downstream services receive the same machine-readable contract.

## Provisional definitions

| Key | Formula | Source service / source | Unit | Permission | Period |
| --- | --- | --- | --- | --- | --- |
| customer_record_count | COUNT(customers.id) WHERE deleted_at IS NULL | Customer / customers | Records | customer.index | Daily as-of |
| unconverted_lead_count | COUNT(customer_leads.id) WHERE deleted_at IS NULL AND converted_customer_id IS NULL | CustomerLead / customer_leads | Records | customer_lead.index | Daily as-of |
| unknown_lead_source_count | COUNT(customer_leads.id) WHERE deleted_at IS NULL AND source_category = 'unknown' | CustomerLead / customer_leads.source_category | Records | customer_lead.index | Daily as-of |
| open_task_count | COUNT(tasks.id) WHERE status != 'done' AND visible_to_viewer | Task / tasks | Records | task.index | Daily as-of |
| overdue_task_count | COUNT(tasks.id) WHERE status != 'done' AND due_date < reporting_local_date AND visible_to_viewer | Task / tasks.due_date,status | Records | task.index | Daily as-of |
| competitor_offer_snapshot_count | COUNT(competitor_offer_observations.id) | CompetitorOfferObservation / append-only observations | Snapshots | competitor_offer.index | Daily as-of |

### Calculation semantics and limitations

- Customer count is not active customers, paying customers, or unique people across merged identities.
- Unconverted lead count follows the converted-customer link, not stored lead status.
- Unknown lead source is an explicit controlled category; legacy free text is not used to infer attribution.
- Task counts include tasks whose status is not exactly done. Unless task.view_all is granted, visibility is limited to tasks assigned to or created by the current viewer.
- A task due on the reporting date is not overdue. The comparison uses the configured reporting timezone.
- Competitor offer snapshot count includes append-only observations for offers later soft-deleted. It is inventory coverage, not market share or source accuracy.
- No KPI in v1 supports dimensions, historical reconstruction, arbitrary date ranges, currency conversion, or financial aggregation.

## Missing and unavailable behavior

- A denied source permission returns a null value with unavailable_permission_denied; it never returns zero.
- A missing source model returns unavailable_missing_source.
- A missing calculation service or unregistered calculation type returns unavailable_missing_calculation.
- A query that cannot produce a value returns an unavailable state with a null value. Missing data is not silently converted to zero.
- Explicit unknown categories remain categories; they are not inferred or discarded.

## Version and change control

The catalogue has its own version (v1). Database-backed KpiDefinition records are effective-dated versions of an existing stable key; they cannot add new executable calculations, source fields, or dimensions. Approved definition semantics are immutable. A semantic change requires a new catalogue release, code-backed calculation contract, synthetic expected-result fixtures, and subsequent domain-owner review. No approval is implied by the existence of a registry entry or a database definition.

## Validation

Run the catalogue and versioned-definition suites:

    php artisan test --compact tests/Feature/ExecutiveKpiCatalogueTest.php tests/Feature/KpiCatalogueRegistryTest.php tests/Feature/KpiDefinitionTest.php

Coverage includes unique stable keys, malformed metadata, unsupported dimensions and period contracts, missing source/calculation handlers, explicit unavailable results, permission gating, synthetic calculation values, and reporting-timezone date boundaries.

## Deferred business validation

Executive/business-owner approval of the final KPI catalogue and definitions is deferred. The current formulas document existing application behavior only. Final business meaning, ownership, real-source reconciliation, historical reporting requirements, and any expansion to additional dimensions or periods require later domain review. No owner sign-off is fabricated or assumed.
