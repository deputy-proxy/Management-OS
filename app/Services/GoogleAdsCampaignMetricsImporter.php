<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class GoogleAdsCampaignMetricsImporter
{
    /** @return array{customer_id: string, from: string, to: string, row_count: int, dry_run: bool} */
    public function import(string $from, string $to, bool $dryRun = false): array
    {
        [$start, $end, $customerId] = $this->validateRequest($from, $to);
        $version = (string) config('services.google_ads.api_version', 'v26');
        $request = $this->apiRequest($this->accessToken());
        $query = sprintf(
            "SELECT campaign.id, campaign.name, segments.date, metrics.impressions, metrics.clicks, metrics.cost_micros, metrics.conversions, customer.currency_code FROM campaign WHERE segments.date BETWEEN '%s' AND '%s' ORDER BY segments.date, campaign.id",
            $start->toDateString(), $end->toDateString(),
        );

        $pageToken = null;
        $rows = [];
        do {
            $payload = ['query' => $query];
            if ($pageToken) {
                $payload['pageToken'] = $pageToken;
            }
            $response = $request->post("https://googleads.googleapis.com/{$version}/customers/{$customerId}/googleAds:search", $payload);
            if (! $response->successful()) {
                $requestId = $response->header('request-id');
                throw new RuntimeException(sprintf('Google Ads API request failed with HTTP %d (request ID: %s). Response content was not logged.', $response->status(), $requestId));
            }
            $body = $response->json();
            if (! is_array($body) || ! is_array($body['results'] ?? null)) {
                throw new RuntimeException('Google Ads API returned an unexpected response shape.');
            }
            foreach ($body['results'] as $result) {
                if (! is_array($result)) {
                    throw new RuntimeException('Google Ads API returned a malformed campaign row.');
                }
                $row = $this->normalizeRow($result, $customerId, $version);
                if ($row['metric_date'] < $start->toDateString() || $row['metric_date'] > $end->toDateString()) {
                    throw new RuntimeException('Google Ads API returned a metric date outside the requested period.');
                }
                $rows[] = $row;
            }
            $pageToken = $body['nextPageToken'] ?? null;
        } while (is_string($pageToken) && $pageToken !== '');

        if (! $dryRun && $rows !== []) {
            DB::transaction(function () use ($rows): void {
                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table('google_ads_campaign_daily_metrics')->upsert(
                        $chunk,
                        ['account_customer_id', 'campaign_id', 'metric_date'],
                        ['campaign_name', 'impressions', 'clicks', 'cost_micros', 'conversions', 'currency_code', 'api_version', 'imported_at'],
                    );
                }
            });
        }

        return ['customer_id' => $customerId, 'from' => $start->toDateString(), 'to' => $end->toDateString(), 'row_count' => count($rows), 'dry_run' => $dryRun];
    }

    /** @return array{CarbonImmutable, CarbonImmutable, string} */
    private function validateRequest(string $from, string $to): array
    {
        $customerId = preg_replace('/\D/', '', (string) config('services.google_ads.customer_id', ''));
        if (! is_string($customerId) || preg_match('/\A[0-9]{10}\z/', $customerId) !== 1) {
            throw ValidationException::withMessages(['customer_id' => 'Google Ads customer ID must be configured as a 10-digit ID.']);
        }
        foreach (['client_id', 'client_secret', 'refresh_token'] as $key) {
            if (blank(config('services.google_ads.'.$key))) {
                throw ValidationException::withMessages(['credentials' => 'Google Ads OAuth credentials are not fully configured.']);
            }
        }
        $loginCustomerId = preg_replace('/\D/', '', (string) config('services.google_ads.login_customer_id', ''));
        if (is_string($loginCustomerId) && $loginCustomerId !== '' && preg_match('/\A[0-9]{10}\z/', $loginCustomerId) !== 1) {
            throw ValidationException::withMessages(['login_customer_id' => 'Google Ads login customer ID must be a 10-digit ID.']);
        }
        try {
            $start = CarbonImmutable::createFromFormat('!Y-m-d', $from, 'UTC');
            $end = CarbonImmutable::createFromFormat('!Y-m-d', $to, 'UTC');
        } catch (\Throwable) {
            $start = false;
            $end = false;
        }
        if (! $start || ! $end || $start->format('Y-m-d') !== $from || $end->format('Y-m-d') !== $to) {
            throw ValidationException::withMessages(['date_range' => 'Import dates must use the exact YYYY-MM-DD format.']);
        }
        if ($start->gt($end) || $end->isFuture() || ($start->diffInDays($end) + 1) > max(1, (int) config('services.google_ads.max_range_days', 90))) {
            throw ValidationException::withMessages(['date_range' => 'The date range must be ordered, not in the future, and within the configured maximum range.']);
        }

        return [$start, $end, $customerId];
    }

    private function accessToken(): string
    {
        $response = Http::asForm()->timeout((int) config('services.google_ads.timeout', 30))->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'refresh_token',
            'client_id' => config('services.google_ads.client_id'),
            'client_secret' => config('services.google_ads.client_secret'),
            'refresh_token' => config('services.google_ads.refresh_token'),
        ]);
        $token = $response->json('access_token');
        if (! $response->successful() || ! is_string($token) || $token === '') {
            throw new RuntimeException(sprintf('Google Ads OAuth token refresh failed with HTTP %d. Response content was not logged.', $response->status()));
        }

        return $token;
    }

    private function apiRequest(string $accessToken): PendingRequest
    {
        $request = Http::withToken($accessToken)->acceptJson()->asJson()->timeout((int) config('services.google_ads.timeout', 30));
        $loginCustomerId = preg_replace('/\D/', '', (string) config('services.google_ads.login_customer_id', ''));
        if (is_string($loginCustomerId) && $loginCustomerId !== '') {
            if (preg_match('/\A[0-9]{10}\z/', $loginCustomerId) !== 1) {
                throw ValidationException::withMessages(['login_customer_id' => 'Google Ads login customer ID must be a 10-digit ID.']);
            }
            $request = $request->withHeaders(['login-customer-id' => $loginCustomerId]);
        }

        return $request;
    }

    /** @param array<string, mixed> $result @return array<string, mixed> */
    private function normalizeRow(array $result, string $customerId, string $version): array
    {
        $campaign = $result['campaign'] ?? null;
        $segments = $result['segments'] ?? null;
        $metrics = $result['metrics'] ?? null;
        $customer = $result['customer'] ?? null;
        if (! is_array($campaign) || ! is_array($segments) || ! is_array($metrics) || ! is_array($customer)) {
            throw new RuntimeException('Google Ads API returned a campaign row missing required fields.');
        }
        $campaignId = (string) ($campaign['id'] ?? '');
        $campaignName = trim((string) ($campaign['name'] ?? ''));
        $metricDate = (string) ($segments['date'] ?? '');
        $currency = strtoupper((string) ($customer['currencyCode'] ?? $customer['currency_code'] ?? ''));
        $impressions = $this->unsignedInteger($metrics['impressions'] ?? null, 'impressions');
        $clicks = $this->unsignedInteger($metrics['clicks'] ?? null, 'clicks');
        $costMicros = $this->unsignedInteger($metrics['costMicros'] ?? $metrics['cost_micros'] ?? null, 'cost');
        $conversions = $metrics['conversions'] ?? null;

        if (preg_match('/\A[0-9]{1,32}\z/', $campaignId) !== 1 || $campaignName === '') {
            throw new RuntimeException('Google Ads API returned an invalid campaign identity.');
        }
        if (preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $metricDate) !== 1 || CarbonImmutable::parse($metricDate)->format('Y-m-d') !== $metricDate) {
            throw new RuntimeException('Google Ads API returned an invalid metric date.');
        }
        if (preg_match('/\A[A-Z]{3}\z/', $currency) !== 1) {
            throw new RuntimeException('Google Ads API returned an invalid account currency.');
        }
        if (! is_string($conversions) && ! is_int($conversions) && ! is_float($conversions)) {
            throw new RuntimeException('Google Ads API returned an invalid conversion metric.');
        }
        $conversions = (string) $conversions;
        if (preg_match('/\A\d{1,12}(?:\.\d{1,6})?\z/', $conversions) !== 1 || ! is_finite((float) $conversions)) {
            throw new RuntimeException('Google Ads API returned an invalid conversion metric.');
        }

        return [
            'account_customer_id' => $customerId,
            'campaign_id' => $campaignId,
            'campaign_name' => mb_substr($campaignName, 0, 255),
            'metric_date' => $metricDate,
            'impressions' => $impressions,
            'clicks' => $clicks,
            'cost_micros' => $costMicros,
            'conversions' => $this->normalizeDecimal($conversions),
            'currency_code' => $currency,
            'api_version' => $version,
            'imported_at' => now(),
        ];
    }

    private function normalizeDecimal(string $value): string
    {
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = ltrim($whole, '0');

        return ($whole === '' ? '0' : $whole).'.'.str_pad($fraction, 6, '0');
    }

    private function unsignedInteger(mixed $value, string $field): string
    {
        $value = (string) $value;
        if (preg_match('/\A[0-9]+\z/', $value) !== 1 || strlen(ltrim($value, '0')) > 19 || (strlen(ltrim($value, '0')) === 19 && strcmp(ltrim($value, '0'), '9223372036854775807') > 0)) {
            throw new RuntimeException('Google Ads API returned an invalid '.$field.' metric.');
        }

        return $value;
    }
}
