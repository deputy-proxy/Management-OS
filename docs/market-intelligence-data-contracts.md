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

## Provider-neutral import adapters (issue #360)

`App\Contracts\MarketIntelligence\MarketDataSourceAdapter` is the provider boundary. Each adapter declares a stable key, whether it is configured, and returns normalized records for a `MarketDataImportRequest`. Adapters must not persist application models or write credentials to request metadata. Add a provider by implementing the contract and registering it with `MarketDataSourceRegistry`; a key that is not registered or reports itself unconfigured is returned as an explicit unavailable import run.

`NormalizedMarketDataRecord` defines the import payload: stable idempotency key, source reference, metric identity, market/segment, value/unit, inclusive period, observation timestamp, optional source URL/revision, methodology, confidence, and source/application metadata. The importer validates arrays into this DTO before persistence; its constructor is private so adapters cannot bypass that validation. Values must be finite numeric values, confidence is bounded to 0..1, and periods must use valid ascending `YYYY-MM-DD` dates. No provider-specific field names are accepted in the normalized contract.

The `mock` adapter emits one deterministic synthetic fixture and marks observations `source_type=synthetic`. The `manual` adapter accepts records supplied to the import request and marks them manual. Both built-in adapters are enabled only in local and testing environments. Neither adapter claims a live source. Unregistered provider keys remain unavailable until a verified adapter is explicitly registered and configured; credentials belong in environment-backed service configuration or a secret manager, never import payloads, ordinary database columns, or logs.

`MarketDataImportService` creates an immutable `MarketDataImportRun` for every attempt. Status is `succeeded`, `failed`, or `unavailable`; attempts, received/imported/skipped counts, timestamps, request filters, typed error, safe error message, and retryability are retained. Retryable transport timeouts are retried up to the configured `services.market_intelligence.max_attempts` (bounded to five); malformed payloads, unavailable sources, and persistence errors are not retried. Import errors are logged with run ID/source key/error type only, and are inspectable in the read-only Marketing > Market Analysis > Market Data Imports resource.

Each normalized record has a source-scoped idempotency key persisted uniquely on `market_trends`. Replaying an import skips already imported records instead of duplicating or rewriting historical observations. Records from one import are persisted transactionally; a failed batch does not leave partial observation writes. The import-run audit record is retained even when the import fails. The import service currently supplies an in-process retry boundary; provider-specific HTTP timeout/backoff and asynchronous scheduling remain the responsibility of a later registered adapter/workflow, not an assumed live integration.

## Model integrity and compatibility (issue #361)

Market trends validate the database decimal precision for values, prior values, percentage changes, and confidence; require valid inclusive periods and bounded identity/source fields; validate source URLs and provenance states; and reject duplicate observations with a null segment at the model boundary (the existing composite unique key continues to protect non-null segment identities). Existing dashboard lookup indexes remain, with additional market/metric/period and freshness/observation indexes. The null-segment guard is application-level to avoid a generated-column or destructive re-key migration against unreviewed historical rows.

Competitor offer prices are widened from integer storage to `DECIMAL(14,2)` so fractional prices are retained without rewriting integer history. Offers validate non-negative bounded prices, currency shape, supported price basis, source URL, and validity/observation timestamps. Each new snapshot receives a stable SHA-256 fingerprint and a unique database key; snapshots are append-only at the model boundary. Soft-deleting an offer preserves its snapshots, while the existing restrictive foreign key prevents force deletion from erasing history. Pre-existing snapshots remain untouched and their nullable fingerprint is not backfilled. The migration rollback refuses to truncate fractional offer prices.

Google Ads daily metrics validate account/campaign identity, non-negative database-range counters, bounded six-decimal conversions, three-letter currency codes, API version, import timestamp, and non-future metric dates. The importer applies the same numeric bounds before its query-builder upsert (which intentionally bypasses Eloquent model events) and rejects provider rows outside the requested date range. Daily account/campaign/date uniqueness remains the idempotency boundary. Added indexes support campaign/date and currency/date reporting; account/campaign configuration and external provider provenance remain separate follow-on work.

All schema changes are additive or precision-widening, preserve existing records, and avoid production backfills. Synthetic migration and regression tests exercise duplicates, date ordering, invalid numeric values, snapshot immutability, soft/hard deletion behavior, and existing dashboard query compatibility.
