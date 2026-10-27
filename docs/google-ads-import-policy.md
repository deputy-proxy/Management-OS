# Google Ads campaign metrics import policy

**Policy version:** `v1`  
**Effective date:** 2026-10-27  
**API version:** `v26`  
**Scope:** Read-only daily campaign reporting from the configured Google Ads account.

## Source and authorization

The importer uses the Google Ads REST `GoogleAdsService.Search` endpoint and GAQL. OAuth client ID, client secret, and refresh token must be supplied through the deployment secret manager. The customer ID and optional manager login customer ID must be 10-digit Ads account IDs. Credentials are never stored in the database or logged. Review the current [authorization guide](https://developers.google.com/google-ads/api/rest/auth) and [Search documentation](https://developers.google.com/google-ads/api/rest/common/search) before API upgrades.

Google's official guidance says developer tokens were sunset on 9 September 2026 and access levels are now associated with Google Cloud projects. The implementation therefore uses OAuth credentials and does not send a developer-token header. Confirm the configured Cloud project has the required API access level before production use: [developer-token/access migration](https://developers.google.com/google-ads/api/docs/api-policy/developer-token).

## Imported grain and fields

One row represents one Ads account, campaign, and calendar date. The natural key is `(account_customer_id, campaign_id, metric_date)`. Fields are campaign name, impressions, clicks, cost in provider micros, conversions, account currency, API version, and import timestamp. Cost is not silently converted to RON or another currency. Provider conversions are not automatically equated to unique converted leads or customer revenue.

## Refresh and idempotency

- Imports require explicit `--from` and `--to` dates in `YYYY-MM-DD` format.
- The range must be ordered, not in the future, and no wider than the configured 90-day maximum by default.
- Repeated imports upsert the same natural key so provider corrections replace prior values rather than duplicating rows.
- All pages are fetched and validated before a transaction writes data. Malformed rows or provider failures fail the import without partial persistence.
- `--dry-run` fetches and validates without writing. A zero-row response is valid and does not delete prior records.
- Routine refresh should re-fetch a bounded recent window to capture attribution adjustments. Scheduling is not enabled by this change.

## Reporting guardrails

- Respect the account timezone implicit in `segments.date`; do not reinterpret it as UTC or Romanian local date.
- Do not combine currencies or infer VAT, refunds, net spend, marketing attribution, CAC, or LTV from these rows.
- Do not match Ads conversions to leads without an approved click/conversion identity and deduplication contract.
- Provider responses and tokens are not logged. Failure messages expose HTTP status and request ID only where available.
- The import is read-only against Ads. It does not create campaigns or change bids, budgets, ads, audiences, or conversion actions.

## Operational setup

Configure `GOOGLE_ADS_CLIENT_ID`, `GOOGLE_ADS_CLIENT_SECRET`, `GOOGLE_ADS_REFRESH_TOKEN`, `GOOGLE_ADS_CUSTOMER_ID`, and optional `GOOGLE_ADS_LOGIN_CUSTOMER_ID` in the deployment secret manager. Run a bounded dry-run first:

```sh
php artisan market:import-google-ads --from=2026-10-01 --to=2026-10-07 --dry-run
```

After output reconciles to the Ads UI, an authorized operator can run without `--dry-run`. Do not put credentials in command-line arguments, shell history, or committed `.env` files.

## Validation

Tests cover pagination, idempotent upserts, currency preservation, manager-account headers, dry-run, invalid date ranges, and malformed provider rows. Live integration remains unverified until valid credentials and account access are configured in deployment.
