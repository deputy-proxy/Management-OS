# Market intelligence configuration and observation contracts

**Status:** Provisional engineering defaults. Business-owner approval and live-source validation remain deferred under [issue #359](https://github.com/deputy-proxy/Management-OS/issues/359).

## Configuration

The Marketing cluster exposes three configuration resources. Access is controlled server-side by:

- `market_intelligence_configuration.view`: list and inspect tracked markets, segments, and metric definitions.
- `market_intelligence_configuration.manage`: create, edit, and delete those definitions.

Tracked markets use stable uppercase codes and a geography type. Segments use stable lowercase codes. Metric definitions include a stable key, category, value type, exact allowed unit codes, expected period granularity, and an optional maximum inclusive period length. No unit conversion or currency normalization is inferred.

The `MarketIntelligenceDefaultsSeeder` provides provisional examples only:

| Kind | Key | Provisional definition |
| --- | --- | --- |
| Market | `RO` | Romania, country |
| Segment | `adult-online` | Adult online |
| Segment | `children` | Children |
| Metric | `search_interest_index` | Index value, unit `index`, search-interest category |
| Metric | `course_package_price` | Currency value, units `RON` or `EUR`, pricing category |

These values are based on existing synthetic test fixtures and are not approved business taxonomy, source definitions, KPI commitments, or live observations. The seeder uses `firstOrCreate`; it does not overwrite edits. Run `php artisan db:seed --class=MarketIntelligenceDefaultsSeeder` to provision missing examples. Remove or replace provisional definitions after an owner approves the actual markets, segments, and metric catalogue.

## Observation compatibility

Existing `MarketTrend` fields and query scopes remain available. Optional foreign keys explicitly link an observation to a configured metric, market, and segment while retaining the existing `metric_key`, `market`, and `segment` values for compatibility with legacy rows and dashboard queries.

When a configured metric key is used, the observation unit must exactly match one of that definition's `allowed_units`. If the metric defines `max_period_days`, the inclusive observation period cannot exceed it. Period start must not follow period end. Unlinked legacy observations are not automatically matched to provisional definitions by their string keys; this preserves existing records until an operator explicitly selects a definition. Unknown legacy metric keys remain storable and are not silently mapped to a definition.

The source and quality dimensions are deliberately independent:

| Field | Values | Meaning |
| --- | --- | --- |
| `data_status` | `present`, `missing` | Whether a numeric observation exists. Missing records have a null value, null confidence, and a required reason; they are never stored as zero. |
| `source_type` | `unknown`, `manual`, `imported`, `synthetic` | How the observation entered the application. Imported records require a stable source reference. |
| `freshness_status` | `unknown`, `current`, `stale` | Whether the observation is considered current under a separately reviewed source-specific freshness rule. No automatic stale threshold is inferred here. |
| `verification_status` | `unverified`, `verified` | Whether source evidence has been checked. Verified records require a source URL or stable source reference. Synthetic records cannot be marked verified. |

The existing `status` field remains the record lifecycle status for backward compatibility. It is not a substitute for freshness, data availability, provenance, or verification.

Confidence remains optional and bounded from 0 to 1. It is not a probability guarantee or verification state. Methodology, source URL, source reference/revision, source metadata, and observation period/time should be retained where known. No live provider is queried by the configuration resources or dashboard.

## Boundaries

- Configuration defaults are explicitly provisional until approved by a business owner.
- The app does not claim a market source is live, licensed, complete, accurate, or verified merely because a URL or source name is stored.
- The app does not calculate market share, demand forecasts, opportunity scores, or cross-currency comparisons.
- Existing competitor, competitor-offer, and Google Ads models remain separate data domains.
- Provider adapters, source terms, cadence, retention, and live-source validation are handled by later issues and owner decisions.
