# Customer acquisition cost and lifetime-value framework

The application now contains a versioned calculation framework, evidence-backed acquisition-cost input, immutable calculation runs, and a permission-gated report page. **No customer-level CAC/LTV policy is approved by the current sales/finance source documents.** The production defaults therefore keep the metric gates disabled and withhold CAC/LTV values.

## Default policy state

The following settings are intentionally `unapproved` in `config/customer-unit-economics.php`:

- `attribution_model`: customer-level attribution from a lead's first-touch snapshot;
- `deduplication`: the rule for multiple leads converted to the same customer;
- `cac_basis`: which cost categories and source-matching rule form the CAC numerator;
- `revenue_basis`: which revenue source and semantics are valid for LTV;
- `ltv_window`: the customer lifetime/observation window;
- `retention_basis` and `retention_policy_version`: the explicit retention evidence and version required to establish the LTV cutoff.

The supported implementation options are present for isolated tests and future owner-approved configuration, but they are **not approved business policy**:

| Setting | Supported implementation option | What it would mean |
| --- | --- | --- |
| `attribution_model` | `first_touch_lead_snapshot` | Use the first-touch snapshot from the verified conversion event for that lead |
| `deduplication` | `earliest_verified_conversion` | Count one customer at the earliest verified conversion in the selected acquisition cohort |
| `cac_basis` | `source_category_matched_entries` | Match evidence-backed acquisition-cost entries to the selected source category and currency |
| `revenue_basis` | `canonical_product_revenue` | Use signed `ProductRevenue.amount_decimal` rows linked through `CustomerInvoice.customer_id` |
| `ltv_window` | `monthly_rows_from_conversion_date` | Include monthly revenue rows whose date is on/after conversion date, through the latest approved retention observation date, without partial-month proration |
| `retention_basis` | `latest_explicit_observation` | Require the latest evidence-backed retained/churned/reactivated observation, recorded under the configured retention policy version, for every acquired customer |

Changing these settings requires explicit Sales/Finance/business-owner approval and matching policy-version and regression-test updates. Merely setting environment variables is not approval.

## Input and calculation controls

Acquisition cost entries require an incurred date, positive decimal amount, ISO currency, source reference, evidence reference, and either an explicit source category or a deliberately blank shared/unattributed category. Entries are append-only. Shared/unattributed costs are surfaced but never silently allocated.

Calculation runs snapshot the configuration, selected period, source record IDs, and exceptions. Runs and results are immutable. Currency totals remain separate; no FX rate is inferred. Retention observations require a customer, date/state, source reference, evidence reference, and policy version; they are never inferred from inactivity, missing interactions, or contract end dates.

When the corresponding policy gates are approved and configured:

- CAC is matched source-category acquisition cost divided by distinct acquired customers for that source and currency.
- Retention is captured as append-only `retained`, `churned`, `reactivated`, or `unknown` observations. Both retained and churned observations cap the revenue window at the latest explicit observation date; no retention is projected beyond the evidence date. Reactivated observations are retained as evidence but are not used for LTV until a reactivation-window rule is approved. Missing, unknown, pre-acquisition, or wrong-policy-version observations withhold LTV.
- LTV is average signed canonical product revenue for the acquired cohort and currency, but only when every cohort customer has both a valid retention observation and revenue coverage in that currency.
- Multiple converted leads, missing conversion events, mismatched customer links, unknown attribution, shared costs, missing invoice links, and incomplete revenue coverage remain explicit exceptions or withheld values.

## Revenue semantics and exclusions

The supported LTV source is operational product-revenue data only. It is not invoiced totals, collected cash, recognized revenue, gross margin, profit, or customer profitability. A signed correction/refund affects the total only if it is already represented as a signed canonical ProductRevenue row. The framework does not infer or reconstruct VAT, discounts, credit notes, bad debt, payment receipt, invoice recognition, or refund mappings from invoice status.

The monthly ProductRevenue date is used as stored. A row dated before the conversion date is excluded; no partial-month allocation is inferred. Mixed currencies are never converted.

## Local and CI validation

Run the focused suite:

```bash
php artisan test tests/Feature/CustomerUnitEconomicsServiceTest.php tests/Feature/CustomerUnitEconomicsReportAuthorizationTest.php
```

The synthetic fixtures cover multiple leads for one customer, missing conversion events, mismatched customer links, unknown attribution, shared costs, mixed currencies, signed correction rows, missing revenue and retention coverage, policy-version mismatches, explicit churn cutoffs, reactivation withholding, immutable inputs/runs, and acquisition/revenue period boundaries. Tests explicitly enable the supported options in-memory; that does not change production defaults.

## Decisions still required

- Sales: customer-level attribution model, treatment of multiple leads, identity/deduplication, and unknown/conflicting attribution.
- Finance: included acquisition cost categories, shared-cost allocation, accounting period, cost-source reconciliation, and CAC numerator.
- Finance: invoice/issued/collected/recognized/net revenue basis, VAT, discounts, credit notes, refunds, bad debt, and FX treatment.
- Business owner: lifetime/retention window, qualifying evidence for retained/churned/reactivated states, churn cutoff, reactivation, and treatment of contract gaps.
- Data owner: permissions, historical coverage, reconciliation evidence, and report freshness.

Until these decisions are approved and configured, the page can collect evidence-backed costs and create auditable runs, but CAC and LTV values remain unavailable by design.
