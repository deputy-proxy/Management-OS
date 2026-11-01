# Market intelligence requirements and readiness audit

## Scope

This audit records the market-intelligence structures and observable dashboard behavior in the repository. It is a technical inventory, not an approved business requirements document, source-provider approval, or market-data validation.

## Current capabilities

| Area | Evidence in current application | Limitations |
| --- | --- | --- |
| Competitor profiles | `Competitor` stores name, site, social profile fields, and a JSON `locations` field. | No verified source provenance, geographic normalization, or periodic profile snapshots. A populated URL field does not establish that the profile is current. |
| Competitor offers | `CompetitorOffer` stores current working data plus source URL, currency, price basis, validity window, and observation time. Material changes append `CompetitorOfferObservation` snapshots with separate observed/recorded timestamps and source-presence status. | Legacy values remain unknown until verified; migration does not fabricate historical snapshots. A source URL is provenance metadata, not proof of accuracy. Currency and price basis must be known before comparisons.
| Market trends | `MarketTrend` observations are permission-gated and displayed on the market analysis dashboard with source, period, observation time, value/unit, market/segment, confidence, and status. | The application displays recorded observations; it does not independently verify source accuracy or harmonize incompatible units, segments, or definitions. Source-specific validation and freshness targets still require evidence.
| Dashboard | `MarketAnalysisDashboardData` reports competitor inventory, offer freshness and observation history, market-trend observations, and imported Google Ads daily metrics. Google Ads aggregates remain separated by currency and are gated by `paid_campaign.index`. | No market-share, demand forecast, or opportunity score is inferred from these records. Dashboard rendering does not call external providers live.
| Advertising | `market:import-google-ads` imports paginated daily campaign metrics through OAuth and GAQL, validates the full result before transactional upsert, and supports dry-run and bounded re-imports. The dashboard displays stored imports. | Live credentials, Cloud-project access level, account authorization, and reconciliation to the Google Ads UI must be verified in deployment. Provider conversions are not unique customer acquisitions or revenue.

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

Google Ads is a separate authorized integration, not a generic public data source. The implemented v1 contract defines customer/manager account scope, OAuth secret storage, campaign/date dimensions, account currency, cost micros, provider conversions, idempotent refresh, bounded date windows, and safe error handling. Deployment still needs account-owner credential setup, Cloud-project access confirmation, quota/refresh monitoring, and reconciliation to the Ads UI before the data is treated as operationally verified.

## Safe next implementation sequence

1. Reconcile imported Google Ads totals and sample campaign-day rows to the authorized account UI.
2. Configure and monitor a bounded daily refresh in deployment only after secrets and account access are verified.
3. Add source-specific validation and freshness thresholds for each `MarketTrend` metric before using it in alerts or forecasts.
4. Add automated source adapters only after source terms, rate limits, collection rights, and provenance are documented.
5. Keep CAC, CLV, customer revenue, market share, and demand forecasts out of the dashboard until customer identity, finance semantics, currency treatment, and expected-result fixtures support them.

## Validation plan

Use synthetic fixtures for overlapping and adjacent periods, repeated imports, source corrections, missing values, invalid units, conflicting sources, timezone boundaries, stale and future-dated observations, unauthorized viewers, provider timeouts/rate limits, and partial import retries. Verify that every displayed value can be traced to source and observation time and that unknown values remain explicitly unknown. Keep credentials and real customer campaign data out of test fixtures.

## Current decision status

Issue #359 adds permissioned configuration for tracked markets, segments, and metric definitions. Initial catalogue entries are explicitly provisional examples based on synthetic fixtures; they are not business-owner-approved taxonomy. Trend observations can link to these definitions while retaining legacy string fields. Missing data is represented without a numeric zero, and source type, freshness, and verification are separate dimensions. These changes do not validate live sources or approve provider terms.


The repository now contains a versioned manual-observation policy and a Google Ads import policy, plus synthetic tests for provenance, idempotency, permission gating, and currency separation. This does not substitute for deployment evidence: no live Google Ads credentials were used during tests, and source accuracy, Cloud-project access, data-use terms, and imported totals still need operational verification. Public-market provider selection and automated collection remain out of scope for this implementation.
