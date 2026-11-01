# Google Ads campaign metrics import policy

**Policy version:** `v1`  
**Effective date:** 2026-10-27  
**API version:** `v26`  
**Scope:** Read-only daily campaign reporting from the configured Google Ads account.

## Source and authorization

The importer uses the Google Ads REST `GoogleAdsService.Search` endpoint and GAQL. OAuth client ID, client secret, and refresh token must be supplied through the deployment secret manager. The customer ID and optional manager login customer ID must be 10-digit Ads account IDs. Credentials are never stored in the database or logged. Review the current [authorization guide](https://developers.google.com/google-ads/api/rest/auth) and [Search documentation](https://developers.google.com/google-ads/api/rest/common/search) before API upgrades.

Google's official guidance says developer tokens were sunset on 9 September 2026 and access levels are now associated with Google Cloud projects. The implementation therefore uses OAuth credentials and does not send a developer-token header. Confirm the configured Cloud project has the required API access level before production use: [developer-token/access migration](https://developers.google.com/google-ads/api/docs/api-policy/developer-token).

## Application configuration and adapter boundary

`GoogleAdsImportConfiguration` stores the account and optional manager customer IDs, a bounded campaign selection, a default date-window length or an explicit fixed date window, the expected account currency, and the canonical metric-field mapping. Empty campaign selection means all campaigns in the configured account. A configured currency is an assertion: a provider response with a different currency is rejected, never converted.

The Filament configuration resource uses the existing `market_intelligence_configuration.view` and `market_intelligence_configuration.manage` permissions. Configuration status is explicit (`ready`, `not_configured`, `disabled`, or `error`); the last safe provider error code and check timestamp are visible to configuration viewers, and a successful check clears the prior error. The importer depends on `GoogleAdsCampaignMetricsAdapter`; the production REST adapter and deterministic mock adapter share the same normalized row contract. Provider errors expose a safe error code and retryability classification without recording response bodies or credentials. The importer validates every normalized row before the existing transaction/upsert boundary, including rows returned by mock adapters.

The database stores only the approved `env:GOOGLE_ADS` credential reference. It never stores OAuth client secrets, refresh tokens, or access tokens. The reference resolves to deployment-managed `GOOGLE_ADS_CLIENT_ID`, `GOOGLE_ADS_CLIENT_SECRET`, and `GOOGLE_ADS_REFRESH_TOKEN` configuration. A missing reference or missing credential makes the integration `not_configured`; it does not trigger a live request. The fixed metric mapping is versioned in application code and stored with each configuration so arbitrary provider fields cannot be injected into GAQL.

## Imported grain and fields

One row represents one Ads account, campaign, and calendar date. The natural key is `(account_customer_id, campaign_id, metric_date)`. Fields are campaign name, impressions, clicks, cost in provider micros, conversions, account currency, API version, and import timestamp. Cost is not silently converted to RON or another currency. Provider conversions are not automatically equated to unique converted leads or customer revenue.

## Refresh and idempotency

- Imports require explicit `--from` and `--to` dates in `YYYY-MM-DD` format.
- The range must be ordered, not in the future, and no wider than the configured 90-day maximum by default.
- Each non-dry-run execution creates a durable import batch with account, requested period, source name/version, status, row count, start/completion timestamps, and a safe error code on failure. The CLI prints the batch ID.
- Repeated imports upsert the same natural key. Exact replays refresh row provenance/freshness without creating a false revision; provider corrections replace current values and write the complete prior metric/provenance snapshot to `google_ads_campaign_daily_metric_revisions`.
- Current metric rows identify their latest successful batch, source name/version, API version, and import timestamp. Batch and revision history is retained for auditability.
- All pages are fetched and every normalized row is validated, including duplicate natural keys and consistent source version, before a transaction writes data. Metric updates, correction snapshots, and successful batch completion commit together. Malformed rows, provider failures, or database errors leave metric data unchanged and mark the batch failed with a safe code.
- `--dry-run` fetches and validates without creating a batch or writing rows. A zero-row response is a completed batch and does not delete prior records or infer zero-valued metrics for campaigns absent from the response.
- Routine refresh should re-fetch a bounded recent window to capture attribution adjustments. Scheduling is not enabled by this change.

## Reporting guardrails

- Respect the account timezone implicit in `segments.date`; do not reinterpret it as UTC or Romanian local date.
- Do not combine currencies or infer VAT, refunds, net spend, marketing attribution, CAC, or LTV from these rows.
- Do not match Ads conversions to leads without an approved click/conversion identity and deduplication contract.
- Provider responses and tokens are not logged. Failure messages expose HTTP status and request ID only where available.
- Use `--configuration=<id>` to run against a saved configuration; without it, the command uses deployment environment defaults. The import is read-only against Ads. It does not create campaigns or change bids, budgets, ads, audiences, or conversion actions.

## Operational setup

Configure `GOOGLE_ADS_CLIENT_ID`, `GOOGLE_ADS_CLIENT_SECRET`, `GOOGLE_ADS_REFRESH_TOKEN`, `GOOGLE_ADS_CUSTOMER_ID`, and optional `GOOGLE_ADS_LOGIN_CUSTOMER_ID` in the deployment secret manager. Run a bounded dry-run first:

```sh
php artisan market:import-google-ads --from=2026-10-01 --to=2026-10-07 --dry-run
```

After output reconciles to the Ads UI, an authorized operator can run without `--dry-run`. Do not put credentials in command-line arguments, shell history, or committed `.env` files.

## Validation

Tests cover pagination, replayed batches, correction snapshots, missing campaigns, source/version/freshness provenance, currency preservation, manager-account headers, dry-run, invalid date ranges, duplicate natural keys, and malformed provider rows. Live integration remains unverified until valid credentials and account access are configured in deployment.
