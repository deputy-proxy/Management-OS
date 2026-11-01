<?php

namespace App\Services\GoogleAds;

use App\Contracts\Advertising\GoogleAdsCampaignMetricsAdapter;
use App\Models\GoogleAdsImportConfiguration;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class GoogleAdsRestCampaignMetricsAdapter implements GoogleAdsCampaignMetricsAdapter
{
    public function fetch(GoogleAdsImportConfiguration $configuration, string $from, string $to): array
    {
        $version = (string) config('services.google_ads.api_version', 'v26');
        if (preg_match('/\Av[0-9]{1,3}\z/', $version) !== 1) {
            throw new GoogleAdsAdapterException('invalid_api_version', 'Google Ads API version configuration is invalid.');
        }

        $request = $this->apiRequest($this->accessToken(), $configuration);
        $query = sprintf(
            "SELECT campaign.id, campaign.name, segments.date, metrics.impressions, metrics.clicks, metrics.cost_micros, metrics.conversions, customer.currency_code FROM campaign WHERE segments.date BETWEEN '%s' AND '%s'",
            $from,
            $to,
        );
        $campaignIds = $configuration->selected_campaign_ids ?? [];
        if ($campaignIds !== []) {
            $query .= ' AND campaign.id IN ('.implode(', ', $campaignIds).')';
        }
        $query .= ' ORDER BY segments.date, campaign.id';

        $rows = [];
        $pageToken = null;
        do {
            $payload = ['query' => $query];
            if ($pageToken) {
                $payload['pageToken'] = $pageToken;
            }
            $response = $request->post("https://googleads.googleapis.com/{$version}/customers/{$configuration->customer_id}/googleAds:search", $payload);
            if (! $response->successful()) {
                $requestId = $response->header('request-id');
                $status = $response->status();
                throw new GoogleAdsAdapterException(
                    'provider_http_'.$status,
                    sprintf('Google Ads API request failed with HTTP %d (request ID: %s). Response content was not logged.', $status, $requestId),
                    $status === 429 || $status >= 500,
                );
            }
            $body = $response->json();
            if (! is_array($body) || ! is_array($body['results'] ?? null)) {
                throw new GoogleAdsAdapterException('invalid_response', 'Google Ads API returned an unexpected response shape.');
            }
            foreach ($body['results'] as $result) {
                if (! is_array($result)) {
                    throw new GoogleAdsAdapterException('invalid_row', 'Google Ads API returned a malformed campaign row.');
                }
                $row = $this->normalizeRow($result, $configuration->customer_id, $version);
                if ($row['metric_date'] < $from || $row['metric_date'] > $to) {
                    throw new GoogleAdsAdapterException('date_out_of_range', 'Google Ads API returned a metric date outside the requested period.');
                }
                if ($campaignIds !== [] && ! in_array($row['campaign_id'], $campaignIds, true)) {
                    throw new GoogleAdsAdapterException('campaign_not_selected', 'Google Ads API returned a campaign outside the configured selection.');
                }
                if ($configuration->currency_code !== null && $row['currency_code'] !== $configuration->currency_code) {
                    throw new GoogleAdsAdapterException('currency_mismatch', 'Google Ads account currency does not match the configured currency.');
                }
                $rows[] = $row;
            }
            $pageToken = $body['nextPageToken'] ?? null;
        } while (is_string($pageToken) && $pageToken !== '');

        return $rows;
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
            throw new GoogleAdsAdapterException(
                'oauth_refresh_failed',
                sprintf('Google Ads OAuth token refresh failed with HTTP %d. Response content was not logged.', $response->status()),
                $response->status() === 429 || $response->status() >= 500,
            );
        }

        return $token;
    }

    private function apiRequest(string $accessToken, GoogleAdsImportConfiguration $configuration): PendingRequest
    {
        $request = Http::withToken($accessToken)->acceptJson()->asJson()->timeout((int) config('services.google_ads.timeout', 30));
        $loginCustomerId = $configuration->login_customer_id;
        if (is_string($loginCustomerId) && $loginCustomerId !== '') {
            if (preg_match('/\A[0-9]{10}\z/', $loginCustomerId) !== 1) {
                throw new GoogleAdsAdapterException('invalid_login_customer_id', 'Google Ads login customer ID must be a 10-digit ID.');
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
            throw new GoogleAdsAdapterException('invalid_row', 'Google Ads API returned a campaign row missing required fields.');
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
            throw new GoogleAdsAdapterException('invalid_campaign', 'Google Ads API returned an invalid campaign identity.');
        }
        $date = GoogleAdsImportConfiguration::strictDate($metricDate);
        if ($date === null) {
            throw new GoogleAdsAdapterException('invalid_metric_date', 'Google Ads API returned an invalid metric date.');
        }
        if (preg_match('/\A[A-Z]{3}\z/', $currency) !== 1) {
            throw new GoogleAdsAdapterException('invalid_currency', 'Google Ads API returned an invalid account currency.');
        }
        if (! is_string($conversions) && ! is_int($conversions) && ! is_float($conversions)) {
            throw new GoogleAdsAdapterException('invalid_conversions', 'Google Ads API returned an invalid conversion metric.');
        }
        $conversions = (string) $conversions;
        if (preg_match('/\A\d{1,12}(?:\.\d{1,6})?\z/', $conversions) !== 1 || ! is_finite((float) $conversions)) {
            throw new GoogleAdsAdapterException('invalid_conversions', 'Google Ads API returned an invalid conversion metric.');
        }

        return [
            'account_customer_id' => $customerId,
            'campaign_id' => $campaignId,
            'campaign_name' => mb_substr($campaignName, 0, 255),
            'metric_date' => $date->toDateString(),
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
        $normalized = ltrim($value, '0');
        if (preg_match('/\A[0-9]+\z/', $value) !== 1 || strlen($normalized) > 19 || (strlen($normalized) === 19 && strcmp($normalized, '9223372036854775807') > 0)) {
            throw new GoogleAdsAdapterException('invalid_'.$field, 'Google Ads API returned an invalid '.$field.' metric.');
        }

        return $value;
    }
}
