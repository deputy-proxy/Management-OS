# Customer Dashboard

For the wider capability map, implemented behavior, regression evidence, and blockers before adding funnel, attribution, retention, or financial customer analytics, see [customer-lifecycle-analytics-audit.md](customer-lifecycle-analytics-audit.md).

The Customer Dashboard is an operational view of customer records, unconverted leads, current contracts, recent interactions, and customer invoice status. It does not infer lifecycle meanings that are not defined in the existing schema.

## Metrics

| Metric | Definition | Date/filter behavior |
| --- | --- | --- |
| Customers | Count of non-soft-deleted `customers` rows | Current snapshot; each customer counted once. |
| Unconverted leads | Count of non-soft-deleted `customer_leads` where `converted_customer_id` is null | Current snapshot; converted leads are excluded because their customer record is the canonical customer. Status is not used as a conversion proxy. |
| Active contracts | Count of non-soft-deleted `customer_contracts` where `start_date <= today` and `end_date >= today` | Inclusive date boundaries in the application timezone. |
| Interactions, last 30 days | Count of non-soft-deleted `customer_interactions` with `timestamp` between now minus 30 days and now | Rolling 30-day window; future timestamps are excluded. |
| Lead status | Count of unconverted leads grouped by stored `status` | Current snapshot. Status labels are shown as stored, without assuming which values mean qualified or lost. |
| Customer invoice status | Count of non-soft-deleted `customer_invoices` grouped by stored `status` | Current calendar month based on the legacy `month` string, which must contain an ISO date to sort into the range. No invoice amount totals are inferred. |
| Recent interactions | Up to eight interactions in the rolling 30-day window | Sorted newest timestamp first; associated customer name is eager-loaded. |
| Recent unconverted leads | Up to eight unconverted leads | Sorted newest creation time first. |

## Authorization

The page has a dedicated `customer-dashboard.view` permission. Each resource-list URL is exposed only when the viewer has the corresponding `customer.index`, `customer_lead.index`, `customer_contract.index`, `customer_interaction.index`, or `customer_invoice.index` permission. The dashboard shows aggregate counts when a destination is restricted, but does not emit unauthorized links.

## Known limitations

- The schema does not define a canonical lead-status enumeration, customer lifecycle status, or archived flag. Dashboard status groups therefore reflect stored values, and soft-deleted rows are excluded by the Eloquent default scope.
- Customer invoices store the billing month as a string and have no currency field. This dashboard counts statuses but does not sum invoice amounts or present receivables.
- Contracts are considered active strictly from their start/end dates; signature state is not used because no agreed rule says it controls activation.
- Recent activity is limited to customer interactions; general model activity logs are not merged into this list because they have different event semantics and authorization requirements.
