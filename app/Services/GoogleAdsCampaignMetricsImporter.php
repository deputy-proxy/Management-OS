<?php

namespace App\Services;

use App\Contracts\Advertising\GoogleAdsCampaignMetricsAdapter;
use App\Models\GoogleAdsImportConfiguration;
use App\Services\GoogleAds\GoogleAdsAdapterException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class GoogleAdsCampaignMetricsImporter
{
    public function __construct(private readonly GoogleAdsCampaignMetricsAdapter $adapter) {}

    /** @return array{customer_id: string, from: string, to: string, row_count: int, dry_run: bool} */
    public function import(string $from, string $to, bool $dryRun = false, ?GoogleAdsImportConfiguration $configuration = null): array
    {
        $configuration ??= $this->environmentConfiguration();
        [$start, $end] = $this->validateRequest($from, $to, $configuration);
        $status = $configuration->configurationStatus();
        if ($status !== 'ready') {
            $message = $status === 'disabled'
                ? 'Google Ads import configuration is disabled.'
                : 'Google Ads integration is not configured. Add the approved deployment secret bundle reference and configure all OAuth credentials in the deployment secret manager.';
            throw ValidationException::withMessages(['configuration' => $message]);
        }

        try {
            $rows = $this->adapter->fetch($configuration, $start->toDateString(), $end->toDateString());
            foreach ($rows as $row) {
                $this->validateNormalizedRow($row, $configuration, $start->toDateString(), $end->toDateString());
            }
        } catch (\Throwable $exception) {
            $configuration->markAdapterError($exception instanceof GoogleAdsAdapterException ? $exception->errorCode : 'invalid_adapter_payload');
            throw $exception;
        }
        $configuration->markAdapterReady();

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

        return [
            'customer_id' => $configuration->customer_id,
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'row_count' => count($rows),
            'dry_run' => $dryRun,
        ];
    }

    /** @param array<string, mixed> $row */
    private function validateNormalizedRow(array $row, GoogleAdsImportConfiguration $configuration, string $from, string $to): void
    {
        $date = is_string($row['metric_date'] ?? null) ? GoogleAdsImportConfiguration::strictDate($row['metric_date']) : null;
        if ($date === null || $date->toDateString() < $from || $date->toDateString() > $to) {
            throw new RuntimeException('Google Ads adapter returned a metric date outside the requested period or an invalid date.');
        }
        if (($row['account_customer_id'] ?? null) !== $configuration->customer_id) {
            throw new RuntimeException('Google Ads adapter returned a row for an unexpected account.');
        }
        if (! is_string($row['campaign_id'] ?? null) || preg_match('/\A[0-9]{1,32}\z/', $row['campaign_id']) !== 1
            || ! is_string($row['campaign_name'] ?? null) || trim($row['campaign_name']) === '' || mb_strlen($row['campaign_name']) > 255) {
            throw new RuntimeException('Google Ads adapter returned an invalid campaign identity.');
        }
        $selected = $configuration->selected_campaign_ids ?? [];
        if ($selected !== [] && ! in_array($row['campaign_id'], $selected, true)) {
            throw new RuntimeException('Google Ads adapter returned a campaign outside the configured selection.');
        }
        if (! is_string($row['currency_code'] ?? null) || preg_match('/\A[A-Z]{3}\z/', $row['currency_code']) !== 1
            || ($configuration->currency_code !== null && $row['currency_code'] !== $configuration->currency_code)) {
            throw new RuntimeException('Google Ads adapter returned a currency that does not match the configured contract.');
        }
        foreach (['impressions', 'clicks', 'cost_micros'] as $field) {
            $value = (string) ($row[$field] ?? '');
            $normalized = ltrim($value, '0');
            if (preg_match('/\A[0-9]+\z/', $value) !== 1 || strlen($normalized) > 19
                || (strlen($normalized) === 19 && strcmp($normalized, '9223372036854775807') > 0)) {
                throw new RuntimeException('Google Ads adapter returned an invalid '.$field.' value.');
            }
        }
        if (! is_string($row['conversions'] ?? null) || preg_match('/\A\d{1,12}(?:\.\d{1,6})?\z/', $row['conversions']) !== 1
            || ! is_finite((float) $row['conversions'])) {
            throw new RuntimeException('Google Ads adapter returned an invalid conversions value.');
        }
        if (! is_string($row['api_version'] ?? null) || preg_match('/\Av[0-9]{1,3}\z/', $row['api_version']) !== 1) {
            throw new RuntimeException('Google Ads adapter returned an invalid API version.');
        }
        if (! ($row['imported_at'] ?? null) instanceof \DateTimeInterface && ! is_string($row['imported_at'] ?? null)) {
            throw new RuntimeException('Google Ads adapter returned a missing import timestamp.');
        }
        try {
            $importedAt = CarbonImmutable::parse($row['imported_at']);
        } catch (\Throwable $exception) {
            throw new RuntimeException('Google Ads adapter returned an invalid import timestamp.', previous: $exception);
        }
        if ($importedAt->lt($date->startOfDay())) {
            throw new RuntimeException('Google Ads adapter import timestamp precedes its metric date.');
        }
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function validateRequest(string $from, string $to, GoogleAdsImportConfiguration $configuration): array
    {
        if (preg_match('/\A[0-9]{10}\z/', (string) $configuration->customer_id) !== 1) {
            throw ValidationException::withMessages(['customer_id' => 'Google Ads customer ID must be configured as a 10-digit ID.']);
        }
        if ($configuration->date_range_days < 1 || $configuration->date_range_days > 90) {
            throw ValidationException::withMessages(['date_range' => 'Google Ads configured date range must be between 1 and 90 days.']);
        }
        $start = GoogleAdsImportConfiguration::strictDate($from);
        $end = GoogleAdsImportConfiguration::strictDate($to);
        if ($start === null || $end === null) {
            throw ValidationException::withMessages(['date_range' => 'Import dates must use the exact YYYY-MM-DD format.']);
        }
        if ($start->gt($end) || $end->isFuture() || ($start->diffInDays($end) + 1) > min(90, (int) config('services.google_ads.max_range_days', 90))) {
            throw ValidationException::withMessages(['date_range' => 'The date range must be ordered, not in the future, and within the configured maximum range.']);
        }
        if ($configuration->date_range_start !== null && $configuration->date_range_end !== null
            && ($from < $configuration->date_range_start->toDateString() || $to > $configuration->date_range_end->toDateString())) {
            throw ValidationException::withMessages(['date_range' => 'Requested dates must stay within the configured fixed date range.']);
        }

        return [$start, $end];
    }

    private function environmentConfiguration(): GoogleAdsImportConfiguration
    {
        return new GoogleAdsImportConfiguration([
            'name' => 'Deployment environment',
            'customer_id' => (string) preg_replace('/\\D/', '', (string) config('services.google_ads.customer_id', '')),
            'login_customer_id' => filled(config('services.google_ads.login_customer_id'))
                ? (string) preg_replace('/\\D/', '', (string) config('services.google_ads.login_customer_id'))
                : null,
            'selected_campaign_ids' => [],
            'date_range_days' => (int) config('services.google_ads.max_range_days', 90),
            'metric_mapping' => GoogleAdsImportConfiguration::METRIC_MAPPING,
            'currency_code' => null,
            'credential_reference' => 'env:GOOGLE_ADS',
            'is_active' => true,
        ]);
    }
}
