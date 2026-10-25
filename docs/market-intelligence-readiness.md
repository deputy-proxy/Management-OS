# Market intelligence requirements and readiness audit

## Scope

This audit records the market-intelligence structures and observable dashboard behavior in the repository. It is a technical inventory, not an approved business requirements document, source-provider approval, or market-data validation.

## Current capabilities

| Area | Evidence in current application | Limitations |
| --- | --- | --- |
| Competitor profiles | `Competitor` stores name, site, social profile fields, and a JSON `locations` field. | No verified source provenance, geographic normalization, or periodic profile snapshots. A populated URL field does not establish that the profile is current. |
| Competitor offers | `CompetitorOffer` stores competitor link, title, free-text offer type/status, description, price, qualitative comparison notes, and `last_check`. | No offer-level source URL, currency, tax/price basis, validity window, or observation history. A current row can be edited in place, so prior values are not a durable time series. |
| Market trends | `MarketTrend` stores name/category/metric key/value/unit/market/segment, period dates, source name/URL, observed timestamp, prior value/change, confidence, methodology, metadata, and status. | This is a general record structure, not evidence that sources, metric definitions, units, confidence scores, or update cadence are governed consistently. The existing dashboard does not integrate trend history. |
| Dashboard | `MarketAnalysisDashboardData` reports inventory, free-text status/type buckets, offer-check freshness, qualitative notes, and permission-aware links. | No verified market-size/share, time-series, audience-demand, engagement, or opportunity score is computed. No live external source is connected in this service. |
| Advertising | The inspected market dashboard/service does not provide Google Ads import or reporting. | Campaign account scope, authorization, dimensions, attribution, cost/conversion semantics, refresh and retention behavior need separate requirements. |

## Requirements decisions needed before integration

The following are decision requests for business owners, not assumptions made by this implementation:

1. **Decisions and users:** identify the management decisions this data should support, intended users, action thresholds, and the cost of stale or incorrect observations.
2. **Metric catalogue:** for each proposed metric, specify business definition, unit, geography, segment, granularity, period, calculation, acceptable source, owner, and whether it is observed, estimated, or third-party reported.
3. **Observation identity:** define how observations are keyed (metric, market, segment, source, effective period), whether multiple sources coexist, and how revisions and duplicates are represented.
4. **Time and freshness:** approve timezone/cutoff, source publication time versus collection time, refresh cadence, stale threshold, late-arriving data behavior, and retention period.
5. **Quality and uncertainty:** define validation ranges, confidence meaning, missing/invalid states, outlier review, estimation labels, and how source disagreements are displayed. Do not silently convert missing values to zero.
6. **Provenance and terms:** record source URL or provider identifier, collection time, methodology/version, access rights and relevant terms, attribution requirements, rate limits, and cost. Do not scrape or import data before access and terms are reviewed.
7. **Permissions and ownership:** name accountable business and technical owners; specify who may view, create, approve, correct, export, and retire data, with audit requirements for corrections.
8. **Success measures:** define a baseline and how decision usefulness, freshness, coverage, and source reliability will be evaluated.

## Provider evaluation criteria

Before selecting public or commercial providers, compare each candidate against the approved metric catalogue. Capture: exact fields and geography available; history depth and update latency; documented API/access method; terms and redistribution limits; authentication and least-privilege scopes; rate limits and reliability commitments; total cost; provenance and correction behavior; known coverage bias; and fallback behavior when unavailable. A provider must not be described as verified until its documentation, access, terms, and representative sample data have been reviewed.

Google Ads is a separate authorized integration, not a generic public data source. Its requirements should identify the customer/manager account scope, required API scopes, campaign and date dimensions, currency/timezone, cost and conversion definitions, attribution window, import idempotency, late corrections, quota handling, secret storage, and account-owner approval before credentials or imports are configured.

## Safe next implementation sequence

1. Obtain owner approval for the decision use cases and metric catalogue.
2. Complete the provider comparison with documented access and terms evidence.
3. Approve the observation identity, provenance, timestamps, units/currency, correction history, permissions, and retention contract.
4. Decide whether `MarketTrend` can represent approved observations or requires a migration. Do not add speculative fields before the contract is agreed.
5. Implement one source adapter with sanitized fixtures, explicit source metadata, idempotent refresh, stale/error states, and permission-aware drill-down.
6. Integrate historical observations into the dashboard only after reconciliation and expected-result fixtures are approved.
7. Treat Google Ads requirements/import/refresh as separate gated milestones; do not use guessed attribution or treat platform conversions as business revenue without an approved definition.

## Validation plan

Use synthetic fixtures for overlapping and adjacent periods, repeated imports, source corrections, missing values, invalid units, conflicting sources, timezone boundaries, stale and future-dated observations, unauthorized viewers, provider timeouts/rate limits, and partial import retries. Verify that every displayed value can be traced to source and observation time and that unknown values remain explicitly unknown. Keep credentials and real customer campaign data out of test fixtures.

## Current decision status

No business-approved metric catalogue, source selection, freshness target, provider access/terms review, or accountable owner approval was identified in the inspected repository documentation. This audit therefore does not recommend a specific provider or authorize an integration. Issues #359 and #360 require stakeholder decisions before implementation; schema and dashboard changes should follow those decisions rather than guess at them.
