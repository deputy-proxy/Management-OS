# Market data structure audit v1

**Status:** Current-state engineering audit; schema changes are not approved by this document  
**Roadmap:** [Issue #361](https://github.com/deputy-proxy/Management-OS/issues/361)

## Scope and evidence boundary

Issue #359 now adds a provisional, permissioned market-intelligence catalogue for tracked markets, segments, and metric definitions, plus explicit observation availability, source type, freshness, and verification fields. This implements an engineering configuration contract without approving business taxonomy, provider access, source terms, or freshness thresholds. See [market intelligence data contracts](market-intelligence-data-contracts.md).


This audit inspects the current `MarketTrend`, `Competitor`, `CompetitorOffer`, `CompetitorOfferObservation`, `GoogleAdsCampaignDailyMetric`, and `MarketAnalysisDashboardData` model/service surfaces. It identifies what those surfaces support and the remaining contract gaps. Issue #359 completes the provisional market-catalogue migration and admin-resource review. This audit still does not claim a live-provider integration test or approve source terms, ingestion cadence, or business definitions.

## Existing data structures

### MarketTrend

The model supports:

- `name`, `category`, and `metric_key` to describe a trend.
- `value`, `unit`, `previous_value`, and `change_pct` for metric values and a change comparison.
- `market`, `segment`, `period_start`, and `period_end` for geography/segment and period context.
- `source_name`, `source_url`, `observed_at`, `methodology`, `confidence`, `metadata`, and `status` for provenance and interpretation.
- Decimal/date/datetime/JSON casts, a saving-time period-order check, confidence range validation, and query scopes for metric/market.

**Gap:** these fields permit recording provenance but do not enforce a source identifier, a documented metric definition, a consistent timezone/cutoff, an explicit provider revision, freshness policy, or immutable historical versions. The model's existence does not prove an active or reliable feed.

### Competitor and CompetitorOffer

- `Competitor` stores a name, website/social profile fields, and locations, and uses soft deletes/activity logging.
- `CompetitorOffer` stores competitor linkage, offer title/type/description, price, currency, price basis, valid-from/until dates, source URL, observed timestamp, status, and comparison notes.
- The offer model exposes an ordered `observations()` relation and validates selected currency/date fields on save.

**Gap:** current profile/offer records represent current editable state. The separate observation model can preserve prior offer states, but correctness depends on write-path behavior, deduplication, correction semantics, and coverage tests. A source URL alone does not record the capture method, provider revision, source terms, or confidence that two offers are comparable.

### CompetitorOfferObservation

The model stores an offer snapshot with title/type/description, price, currency, price basis, source URL, validity dates, observation time, record time, recorder, and source status. It relates back to the offer and recorder.

**Gap:** the inspected model does not itself declare an immutable-write policy or uniqueness/idempotency key. Audit history, delete behavior, and whether every update creates a snapshot must be verified in the owning service/admin workflow and tests. Do not assume snapshots are immutable merely because they have separate rows.

### GoogleAdsCampaignDailyMetric

The model stores account customer ID, campaign ID/name, metric date, impressions, clicks, cost in micros, conversions, currency code, API version, and import timestamp.

**Gap:** the model alone does not define an import run, query/report version, date-window completeness, partial-failure state, late revisions, account authorization state, campaign mapping to internal products, or source freshness. Counts and monetary metrics need non-negative/validity checks and explicit semantics; conversions can be fractional. Provider-attributed conversions must not be presented as recognized revenue or profit.

### MarketAnalysisDashboardData

The service accepts explicit permission flags for competitor, offer, and Google Ads views and conditionally builds summary data. This is an existing integration point and should be extended rather than replaced. The inspected surface includes freshness-window logic for Google Ads metrics and summary data for competitor/offer/history/ads domains.

**Gap:** freshness needs to be source-specific and visible as status, not just inferred from a date-window count. Trend observations and their quality/provenance should be integrated only after agreeing on definitions and permissions. Tests should ensure denied permissions do not leak details through aggregates or drill-down.

## Cross-cutting contract gaps

1. **Metric definitions:** no common registry guarantees definition, unit, grain, owner, formula, and allowed comparison dimensions for each trend/ads metric.
2. **Source identity and revision:** URLs and source names are useful but do not always provide stable source/dataset IDs, query parameters, provider API version, revision IDs, or content fingerprints.
3. **Time semantics:** period start/end and observed/imported timestamps exist in several models, but the shared contract for timezone, reporting cutoff, observation-vs-valid time, and corrections is not established.
4. **Currency and price basis:** offers and ad metrics carry currency fields, but compatible aggregation, unknown-currency handling, taxes/fees, and conversion policy remain outside the model contract. Do not sum across currencies or compare unlike price bases.
5. **History and idempotency:** the observation model supports historical snapshots, but uniqueness, repeated imports, duplicate manual capture, corrections, and retention behavior need explicit rules and tests.
6. **Freshness and failure state:** stale data, last successful refresh, partial imports, unavailable credentials, and provider errors need explicit persisted/derived states. Failed refreshes must not erase the last valid observation or look like zero.
7. **Mapping and comparability:** campaign-to-internal-campaign/product mapping and competitor-offer equivalence are not established by the inspected model fields. Ambiguous mappings should remain exceptions.
8. **Access and retention:** existing permission checks must cover source detail and exports as well as dashboard summaries. Retention/deletion should follow source terms and internal policy.
9. **Observability:** source coverage, refresh success, mapping coverage, stale count, duplicate rate, and exception age need operational metrics and operator instructions.

## Recommended next changes, in dependency order

### First: complete verification without changing schema

- Trace migrations, admin/resource forms, model observers/events, ingestion jobs/services, policies/permissions, and tests for all five data models.
- Confirm whether `MarketTrend` ingestion exists, whether offer updates reliably append observations, and whether Google Ads import is present or only its storage/dashboard surface.
- Document exact source-specific fields and existing behavior, including any gaps between model fillable fields and form validation.

### Second: approve contracts

Use the requirements in [market intelligence requirements v1](market-intelligence-requirements-v1.md) and [public market source evaluation v1](public-market-source-evaluation-v1.md) as proposals pending owner review. Approve metric definitions, source identity, cadence/stale thresholds, timestamp semantics, retention, currency behavior, and permission ownership before adding fields.

### Third: implement only justified changes

Potential additions, subject to approved requirements and migration review:

- A source/metric definition or version identifier where existing fields cannot provide stable semantics.
- Import-run/status metadata for partial failure, coverage windows, and last-successful refresh.
- Stable provider/source IDs and idempotency keys for repeatable ingestion.
- Explicit trend quality/freshness output in the existing dashboard.
- Tests for immutable historical offer observations, duplicate imports, revisions, stale/failure states, currency mismatch, permission boundaries, and regression of existing dashboard behavior.

Do not add all of these fields speculatively. First verify whether existing tables/services already cover them and whether an owner-approved source requires them.

## Regression and reconciliation test plan

- Existing competitor/offer dashboard values and permission behavior remain unchanged for users with current access.
- Unauthorized users cannot infer protected counts, names, source URLs, campaign IDs, or detailed observations.
- Repeated import of the same provider/account/campaign/date/metric data is idempotent.
- Partial page or provider failure records an error/stale state and preserves the last valid observation.
- Provider corrections/revisions have deterministic history behavior.
- Offer changes append a snapshot with source, observation timestamp, currency, price basis, and validity terms; duplicate capture behavior is explicit.
- Invalid period ordering, unknown currency, missing source provenance, and incompatible price bases are rejected or surfaced as exceptions.
- Trend values remain distinguishable from estimates and provider-derived values; units and geography are not silently mixed.
- Dashboard freshness and source coverage reconcile to the actual rows and latest successful import metadata.

## Acceptance status

This audit records the inspected model/service capabilities and cross-cutting contract gaps. It does not approve schema changes, certify source terms, claim a complete admin/migration audit, or establish that any provider is connected. The next safe step is to finish repository-path verification and obtain owner approval for the data contracts before implementation.
