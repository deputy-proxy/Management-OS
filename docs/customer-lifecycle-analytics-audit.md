# Customer lifecycle and sales analytics capability audit

**Status:** repository-backed capability audit; no new sales policy or attribution rules approved by this document.
**Reviewed:** 2026-10-25.
**Scope:** lead lifecycle, lead-to-customer conversion, contracts, customer interactions, invoices, and the existing Customer Dashboard.

## Existing capabilities and source of truth

| Capability | Existing implementation / source | What can be reported now | Evidence and limitations |
| --- | --- | --- | --- |
| Lead lifecycle | `CustomerLead`, `CustomerLead::statuses()`, `CustomerLeadResource`; status transitions enforced in `CustomerLead::booted()` | Current counts by stored status; current unconverted leads; recent leads; last-contacted timestamp | Allowed statuses are `new`, `contacted`, `qualified`, `proposal`, `converted`, and `lost`. Transition validation exists and is covered by `CustomerLeadLifecycleTest`. Lost leads can be reopened to `new`; converted leads cannot return to the active pipeline. |
| Lead conversion | `ConvertCustomerLead`; `converted_customer_id`, `converted_at`; `CustomerLeadConverted` event | Conversion can be traced from lead to canonical customer and signed contract; conversion timestamp is recorded | Conversion requires operator confirmation that the uploaded contract is signed. It locks the lead, checks identity/email collisions including soft-deleted customers, creates/reuses the customer and creates a contract in a transaction. Conversion tests exist in `CustomerLeadConversionTest`. This does not establish an approved sales attribution model. |
| Customer records | `Customer` and `CustomerResource` | Count of non-soft-deleted customers; customer identity and type | There is no canonical active/inactive/churned customer lifecycle field in the audited model. Do not infer churn from inactivity or a missing interaction. |
| Contracts and renewals | `CustomerContract` (`start_date`, `end_date`, `signed_at`, `source_customer_lead_id`) | Current date-window contract count; contract records linked to a source lead where available | The dashboard treats a contract as active when `start_date <= today <= end_date`. There is no approved renewal, cancellation, auto-renewal, or churn definition. End date alone is not proof that a customer churned. |
| Customer interactions | `CustomerInteraction` (`customer_id`, `timestamp`, `initiator`, `reason`, `description`, `interaction_rating`) | Rolling 30-day interaction count and the eight most recent interactions | Interactions are customer-linked, not directly lead-linked before conversion. No common activity taxonomy or complete historical interaction coverage is established. |
| Customer invoices | `CustomerInvoice` and `CustomerInvoiceResource` | Current-calendar-month counts grouped by stored status | The audited dashboard does not sum amounts or report receivables. `month` is a legacy string field and the dashboard has no currency-aware aggregation. Invoice status is shown as stored; no sales-revenue attribution should be inferred from these counts. |
| Source attribution | `CustomerLead.source`, `topic_interest`, `product_interest` | Raw lead source and interests are available in lead records | `source` is free text; no controlled taxonomy, campaign/referral identifier, first-touch/last-touch rule, attribution history, or verified conversion attribution is implemented. Existing records must remain unknown when provenance is absent. |
| Executive/customer overview | `CustomerDashboardData`, `CustomerDashboard`, `resources/views/filament/clusters/finance/pages/customer-dashboard.blade.php` | Customers, unconverted leads, active contracts, interactions in last 30 days, lead status counts, current-month invoice status counts, recent leads/interactions | The dashboard is an operational snapshot, not a conversion funnel, cohort-retention report, CAC/CLV calculation, or revenue dashboard. It exposes resource links only when the matching list permission is granted. |
| Auditability and access | Eloquent soft deletes/activity logging; HexaLite page/resource permissions | Existing activity log fields and resource permission gates | Audit events record selected field changes, not a complete immutable event stream of every historical lifecycle transition. Dashboard aggregate counts are shown even when the underlying list link is restricted; verify role expectations before adding more sensitive dimensions. |

## Definitions currently supported

- **Unconverted lead:** a non-soft-deleted `customer_leads` row with `converted_customer_id IS NULL`. Status alone is not used as a conversion proxy.
- **Conversion timestamp:** `converted_at`, written by the conversion action. It can support elapsed-time calculations only after missing/legacy timestamp handling is agreed and validated.
- **Active contract:** a non-soft-deleted contract whose start and end dates inclusively contain the application-local current date. This is the dashboard's operational date-window definition, not an approved renewal/churn rule.
- **Recent interaction:** a non-soft-deleted interaction with timestamp in the rolling 30-day window ending at the dashboard's evaluation time.
- **Lead stage count:** a count by stored status among unconverted leads. It is not a funnel conversion rate because the dashboard does not retain a complete stage-transition history or cohort definition.

## Gaps before extending analytics

1. **Controlled source taxonomy:** business owner must approve allowed source values, campaign/referral identifiers, treatment of unknown/legacy values, and whether attribution is first-touch, last-touch, or another policy. Do not backfill guesses into existing records.
2. **Funnel and cycle-time definitions:** approve cohort date, stage-entry semantics, reopened/lost lead handling, conversion denominator, reporting timezone, and treatment of missing timestamps. Current status is mutable and cannot reconstruct historical stage durations reliably.
3. **Retention and churn:** define a customer cohort, qualifying activity/contract event, renewal grace period, cancellation semantics, observation window, and censoring of incomplete periods. Missing activity must not be equated with churn.
4. **CAC and lifetime value:** requires approved cost attribution, revenue source, contract/invoice recognition semantics, customer identity deduplication, currency handling, retention horizon, and explicit assumptions. Current invoice status counts alone are insufficient.
5. **Historical integrity:** inventory duplicate/ambiguous identities, source coverage, timestamps, soft-deleted records, and contract links before any migration/backfill. Preserve source values and record provenance; do not rewrite historical financial records to fit a metric.
6. **Permissions and freshness:** verify which roles may see aggregate versus record-level dimensions, define refresh cadence and stale-data presentation, and ensure new reports link to authorized source records only.

## Existing regression evidence

- `tests/Feature/CustomerDashboardDataTest.php` covers unconverted-lead counting, soft-delete exclusion, inclusive contract date boundaries, the rolling interaction window, current-month invoice status counts, and empty data.
- `tests/Feature/Filament/CustomerLeadLifecycleTest.php` covers lifecycle status and transition rules.
- `tests/Feature/Filament/CustomerLeadConversionTest.php` covers conversion behavior and its guardrails.
- `tests/Feature/SendCustomerLeadContractTest.php` covers contract-delivery workflow behavior.

Run these focused tests with the project's normal test environment before extending the analytics. A passing fixture suite establishes implementation behavior for those cases, not completeness or accuracy of production history.

## Recommended sequence

1. Approve source taxonomy and lifecycle/funnel definitions before adding attribution fields or conversion-rate charts.
2. Audit existing production-like data for missing/legacy statuses, timestamps, source values, and lead/customer/contract collisions; report counts and examples without exposing personal data.
3. Add source attribution with explicit unknown handling and immutable provenance only after the taxonomy and history requirements are approved.
4. Build funnel and retention analytics against synthetic fixtures with independently calculated expected results, preserving current conversion semantics.
5. Consider CAC/CLV only after finance-approved revenue/cost contracts, currency and time-window rules, and data-quality gates exist.

No production data was queried or changed for this repository audit. No attribution, churn, revenue, CAC, or lifetime-value policy is inferred from the presence of existing fields.
