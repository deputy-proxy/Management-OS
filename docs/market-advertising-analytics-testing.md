# Market and advertising analytics test guide

## Run the focused workflow suite

Run the end-to-end synthetic import and dashboard reconciliation tests:

    php artisan test --compact tests/Feature/MarketAdvertisingAnalyticsWorkflowTest.php

Run the market and advertising regression suites together:

    php artisan test --compact \
      tests/Feature/MarketTrendTest.php \
      tests/Feature/MarketDataImportAdaptersTest.php \
      tests/Feature/MarketIntelligenceConfigurationTest.php \
      tests/Feature/MarketAnalysisDashboardDataTest.php \
      tests/Feature/GoogleAdsCampaignMetricsImporterTest.php \
      tests/Feature/GoogleAdsImportConfigurationTest.php \
      tests/Feature/GoogleAdsRefreshRecoveryTest.php \
      tests/Feature/MarketAdvertisingAnalyticsWorkflowTest.php

The CI quality gate remains composer test; the focused commands are for local diagnosis. Tests use deterministic records, the manual market adapter, and the mock Google Ads adapter. HTTP tests fake provider requests, and tests must not require provider credentials, real accounts, or live network access.

## Supported and asserted application states

| Workflow | Supported state or behavior | Expected application result |
| --- | --- | --- |
| Market data import | succeeded | Valid normalized records persist; replayed source identities are skipped without rewriting observations. |
| Market data import | failed | Malformed records and persistence failures are recorded; a malformed batch does not partially persist earlier records. |
| Market data import | unavailable | Unknown or unconfigured sources create a visible import-run audit record without calling a provider. |
| Google Ads import batch | completed | Valid campaign-day rows are inserted, updated with revisions, or counted unchanged transactionally; an empty response is a completed zero-row batch. |
| Google Ads import batch | failed | Malformed payloads and provider/persistence failures retain a safe error code and retryability state; partial metric writes are rolled back. |
| Google Ads health | healthy, incomplete, stale, retryable_error, invalid_data, error | Health distinguishes complete/fresh coverage, missing period coverage, old data, retryable provider failure, invalid currency/data, and non-retryable failure. |
| Market dashboard | active observations, including explicit missing values | Source, observation period, provenance, freshness, verification, and missing reason remain visible; missing values are not replaced with zero. |
| Advertising dashboard | rows within the inclusive 30-day window through the supplied current date | Campaign and currency totals reconcile to stored campaign-day rows; unlike currencies remain separate. Rows outside the window are excluded. |
| Dashboard authorization | market-trend and paid-campaign permissions | Unauthorized sections expose no counts, totals, records, or filter options for that data domain. |
| Empty advertising dataset | no qualifying campaign-day rows | Row and campaign counts are zero, totals are empty, and the dashboard renders its explicit empty state. |

These are application-level tests against synthetic data. They do not establish live provider access, source accuracy, production account reconciliation, or business approval of provisional market definitions.
