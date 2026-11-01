<?php

namespace Tests\Feature;

use App\Contracts\Advertising\GoogleAdsCampaignMetricsAdapter;
use App\Models\GoogleAdsCampaignDailyMetric;
use App\Models\GoogleAdsImportBatch;
use App\Models\GoogleAdsImportConfiguration;
use App\Services\GoogleAds\GoogleAdsAdapterException;
use App\Services\GoogleAds\MockGoogleAdsCampaignMetricsAdapter;
use App\Services\GoogleAdsCampaignMetricsHealthService;
use App\Services\GoogleAdsCampaignMetricsImporter;
use App\Services\GoogleAdsCampaignMetricsRefreshService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GoogleAdsRefreshRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.timezone' => 'UTC',
            'services.google_ads.client_id' => 'client-id',
            'services.google_ads.client_secret' => 'client-secret',
            'services.google_ads.refresh_token' => 'refresh-token',
            'services.google_ads.client_id' => 'client-id',
            'services.google_ads.client_secret' => 'client-secret',
            'services.google_ads.refresh_token' => 'refresh-token',
            'services.google_ads.api_version' => 'v26',
            'services.google_ads.stale_after_hours' => 36,
            'services.google_ads.retry_base_delay_minutes' => 5,
        ]);
    }

    public function test_retryable_provider_failure_can_be_retried_as_a_new_idempotent_batch(): void
    {
        $configuration = GoogleAdsImportConfiguration::query()->create($this->configurationAttributes());
        $adapter = new class implements GoogleAdsCampaignMetricsAdapter
        {
            public int $calls = 0;

            public function fetch(GoogleAdsImportConfiguration $configuration, string $from, string $to): array
            {
                $this->calls++;
                if ($this->calls === 1) {
                    throw new GoogleAdsAdapterException('provider_http_503', 'Temporary provider failure.', true);
                }

                return [[
                    'account_customer_id' => $configuration->customer_id,
                    'campaign_id' => '1001',
                    'campaign_name' => 'Campaign One',
                    'metric_date' => $from,
                    'impressions' => '100',
                    'clicks' => '10',
                    'cost_micros' => '100000',
                    'conversions' => '2.000000',
                    'currency_code' => 'RON',
                    'api_version' => 'v26',
                    'imported_at' => $to.' 12:00:00',
                ]];
            }
        };
        $this->app->instance(GoogleAdsCampaignMetricsAdapter::class, $adapter);
        $importer = app(GoogleAdsCampaignMetricsImporter::class);

        try {
            $importer->import('2026-10-01', '2026-10-01', false, $configuration);
            $this->fail('The synthetic provider should fail once.');
        } catch (GoogleAdsAdapterException) {
            $this->assertDatabaseHas('google_ads_import_batches', [
                'status' => 'failed', 'error_code' => 'provider_http_503', 'retryable' => true,
                'failure_message' => 'The provider request failed temporarily. Retry this date range after the provider recovers.',
            ]);
        }

        $failedBatch = GoogleAdsImportBatch::query()->firstOrFail();
        $this->assertSame($failedBatch->started_at->addMinutes(5)->toDateTimeString(), $failedBatch->next_retry_at?->toDateTimeString());
        try {
            app(GoogleAdsCampaignMetricsRefreshService::class)->retry($failedBatch, $failedBatch->started_at);
            $this->fail('Retry must respect the provider backoff window.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('delayed until', collect($exception->errors())->flatten()->implode(' '));
        }
        $retry = app(GoogleAdsCampaignMetricsRefreshService::class)->retry($failedBatch, $failedBatch->next_retry_at);
        $successfulRetry = GoogleAdsImportBatch::query()->findOrFail($retry['retry_batch_id']);

        $this->assertSame('completed', $successfulRetry->status);
        $this->assertSame($failedBatch->getKey(), $successfulRetry->retry_of_batch_id);
        $this->assertSame(1, $successfulRetry->rows_received);
        $this->assertSame(1, $successfulRetry->rows_inserted);
        $this->assertSame(1, GoogleAdsCampaignDailyMetric::query()->count());
        $this->assertSame('ready', $configuration->fresh()->configurationStatus());

        // Duplicate delivery of the original retry request must reuse the same child batch.
        $duplicateRetry = app(GoogleAdsCampaignMetricsRefreshService::class)->retry($failedBatch, $failedBatch->next_retry_at);
        $this->assertSame($retry['retry_batch_id'], $duplicateRetry['retry_batch_id']);
        $this->assertSame('completed', $duplicateRetry['status']);
        $this->assertSame(2, $adapter->calls);
        $this->assertDatabaseCount('google_ads_import_batches', 2);
        $this->assertDatabaseCount('google_ads_campaign_daily_metrics', 1);
    }

    public function test_scheduled_refresh_command_exits_unsuccessfully_when_a_configuration_fails(): void
    {
        $configuration = GoogleAdsImportConfiguration::query()->create($this->configurationAttributes());
        $adapter = new class implements GoogleAdsCampaignMetricsAdapter
        {
            public function fetch(GoogleAdsImportConfiguration $configuration, string $from, string $to): array
            {
                throw new GoogleAdsAdapterException('provider_http_503', 'Synthetic provider outage.', true);
            }
        };
        $this->app->instance(GoogleAdsCampaignMetricsAdapter::class, $adapter);

        $this->artisan('market:refresh-google-ads')
            ->expectsOutput('Google Ads refresh: 0 completed, 1 failed, 0 skipped (of 1 active configurations).')
            ->assertExitCode(1);

        $this->assertDatabaseHas('google_ads_import_batches', [
            'configuration_id' => $configuration->getKey(),
            'status' => 'failed',
            'error_code' => 'provider_http_503',
            'retryable' => true,
        ]);
    }

    public function test_non_retryable_payload_failures_are_not_retried(): void
    {
        $configuration = GoogleAdsImportConfiguration::query()->create($this->configurationAttributes());
        $invalid = $this->row(['campaign_id' => 'invalid']);
        $this->app->instance(GoogleAdsCampaignMetricsAdapter::class, new MockGoogleAdsCampaignMetricsAdapter([$invalid]));

        try {
            app(GoogleAdsCampaignMetricsImporter::class)->import('2026-10-01', '2026-10-01', false, $configuration);
            $this->fail('Invalid synthetic payload should fail.');
        } catch (\RuntimeException) {
            $batch = GoogleAdsImportBatch::query()->firstOrFail();
            $this->assertFalse($batch->retryable);
            $this->assertSame('The provider payload failed validation. Correct the source response or mapping before retrying.', $batch->failure_message);
            $this->expectException(ValidationException::class);
            app(GoogleAdsCampaignMetricsRefreshService::class)->retry($batch);
        }
    }

    public function test_database_failure_rolls_back_partial_rows_and_a_fresh_batch_recovers(): void
    {
        $configuration = GoogleAdsImportConfiguration::query()->create($this->configurationAttributes());
        $rows = [
            $this->row(['campaign_id' => '1001']),
            $this->row(['campaign_id' => '1002']),
        ];
        $adapter = new MockGoogleAdsCampaignMetricsAdapter($rows);
        $this->app->instance(GoogleAdsCampaignMetricsAdapter::class, $adapter);
        $inserts = 0;
        $failSecondInsert = true;
        DB::listen(function (QueryExecuted $query) use (&$inserts, &$failSecondInsert): void {
            if ($failSecondInsert
                && str_starts_with(strtolower(ltrim($query->sql)), 'insert into')
                && str_contains($query->sql, 'google_ads_campaign_daily_metrics')) {
                $inserts++;
                if ($inserts === 2) {
                    $failSecondInsert = false;
                    throw new QueryException(
                        DB::connection()->getName(),
                        $query->sql,
                        $query->bindings,
                        new \PDOException('Synthetic persistence failure.'),
                    );
                }
            }
        });

        try {
            app(GoogleAdsCampaignMetricsImporter::class)->import('2026-10-01', '2026-10-01', false, $configuration);
            $this->fail('The synthetic second insert should fail.');
        } catch (QueryException) {
            $this->assertDatabaseCount('google_ads_campaign_daily_metrics', 0);
            $this->assertDatabaseHas('google_ads_import_batches', [
                'status' => 'failed', 'rows_received' => 2, 'retryable' => false,
                'error_code' => 'persistence_failed',
                'failure_message' => 'The database transaction failed. No metric rows were committed; inspect database diagnostics before starting a fresh import.',
            ]);
        }

        $recovery = app(GoogleAdsCampaignMetricsImporter::class)->import('2026-10-01', '2026-10-01', false, $configuration);
        $this->assertSame(2, $recovery['row_count']);
        $this->assertDatabaseCount('google_ads_campaign_daily_metrics', 2);
        $this->assertDatabaseHas('google_ads_import_batches', ['id' => $recovery['batch_id'], 'status' => 'completed']);
    }

    public function test_refresh_uses_the_configured_bounded_period_and_accepts_empty_provider_data(): void
    {
        $configuration = GoogleAdsImportConfiguration::query()->create($this->configurationAttributes(['date_range_days' => 3]));
        $this->app->instance(GoogleAdsCampaignMetricsAdapter::class, new MockGoogleAdsCampaignMetricsAdapter([]));
        $now = CarbonImmutable::parse('2026-11-01 08:00:00', 'UTC');

        $result = app(GoogleAdsCampaignMetricsRefreshService::class)->refresh($configuration, $now);
        $batch = GoogleAdsImportBatch::query()->findOrFail($result['batch_id']);

        $this->assertSame('2026-10-29', $batch->period_start->toDateString());
        $this->assertSame('2026-10-31', $batch->period_end->toDateString());
        $this->assertSame('completed', $batch->status);
        $this->assertSame(0, $batch->rows_received);
        $this->assertSame(0, GoogleAdsCampaignDailyMetric::query()->count());
    }

    public function test_refresh_uses_the_ads_account_timezone_for_the_default_date_window(): void
    {
        $configuration = GoogleAdsImportConfiguration::query()->create($this->configurationAttributes([
            'date_range_days' => 3, 'account_timezone' => 'America/Los_Angeles',
        ]));
        $this->app->instance(GoogleAdsCampaignMetricsAdapter::class, new MockGoogleAdsCampaignMetricsAdapter([]));
        $now = CarbonImmutable::parse('2026-11-01 00:15:00', 'UTC');

        $result = app(GoogleAdsCampaignMetricsRefreshService::class)->refresh($configuration, $now);
        $batch = GoogleAdsImportBatch::query()->findOrFail($result['batch_id']);

        $this->assertSame('2026-10-28', $batch->period_start->toDateString());
        $this->assertSame('2026-10-30', $batch->period_end->toDateString());
    }

    public function test_invalid_account_timezone_is_rejected_at_configuration_save(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        GoogleAdsImportConfiguration::query()->create($this->configurationAttributes(['account_timezone' => 'Mars/Olympus_Mons']));
    }

    public function test_refresh_command_skips_unconfigured_accounts_without_calling_provider(): void
    {
        GoogleAdsImportConfiguration::query()->create($this->configurationAttributes(['credential_reference' => null]));
        GoogleAdsImportConfiguration::query()->create($this->configurationAttributes(['name' => 'Timezone missing', 'account_timezone' => null]));
        $this->app->instance(GoogleAdsCampaignMetricsAdapter::class, new MockGoogleAdsCampaignMetricsAdapter([]));

        $exitCode = Artisan::call('market:refresh-google-ads');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('0 completed, 0 failed, 2 skipped', Artisan::output());
        $this->assertDatabaseCount('google_ads_import_batches', 0);
    }

    public function test_health_service_distinguishes_stale_incomplete_and_healthy_data(): void
    {
        $configuration = GoogleAdsImportConfiguration::query()->create($this->configurationAttributes(['date_range_days' => 3]));
        $health = app(GoogleAdsCampaignMetricsHealthService::class);
        $now = CarbonImmutable::parse('2026-11-01 08:00:00', 'UTC');

        $initial = $health->inspect($configuration, $now);
        $this->assertSame('stale', $initial['status']);
        $this->assertTrue($initial['incomplete_period']);

        GoogleAdsImportBatch::query()->create([
            'configuration_id' => $configuration->getKey(),
            'account_customer_id' => $configuration->customer_id,
            'period_start' => '2026-10-30',
            'period_end' => '2026-10-31',
            'source_name' => 'google_ads',
            'source_version' => 'v26',
            'status' => 'completed',
            'row_count' => 0,
            'started_at' => '2026-10-31 03:00:00',
            'completed_at' => '2026-10-31 04:00:00',
        ]);
        $partial = $health->inspect($configuration, $now);
        $this->assertSame('incomplete', $partial['status']);
        $this->assertTrue($partial['incomplete_period']);

        GoogleAdsImportBatch::query()->create([
            'configuration_id' => $configuration->getKey(),
            'account_customer_id' => $configuration->customer_id,
            'period_start' => '2026-10-29',
            'period_end' => '2026-10-31',
            'source_name' => 'google_ads',
            'source_version' => 'v26',
            'status' => 'completed',
            'row_count' => 0,
            'rows_received' => 0,
            'started_at' => '2026-10-31 04:00:00',
            'completed_at' => '2026-10-31 05:00:00',
        ]);

        $healthy = $health->inspect($configuration, $now);
        $this->assertSame('healthy', $healthy['status']);
        $this->assertFalse($healthy['stale']);
        $this->assertFalse($healthy['incomplete_period']);

        GoogleAdsImportBatch::query()->create([
            'configuration_id' => $configuration->getKey(),
            'account_customer_id' => $configuration->customer_id,
            'period_start' => '2026-10-29',
            'period_end' => '2026-10-31',
            'source_name' => 'google_ads',
            'source_version' => 'v26',
            'status' => 'failed',
            'error_code' => 'provider_http_503',
            'retryable' => true,
            'started_at' => '2026-11-01 06:00:00',
            'completed_at' => '2026-11-01 06:01:00',
        ]);
        $failed = $health->inspect($configuration, $now);
        $this->assertSame('retryable_error', $failed['status']);
        $this->assertSame('provider_http_503', $failed['latest_error_code']);
        $this->assertTrue($failed['retryable']);
    }

    public function test_health_service_flags_currency_changes_without_converting_or_filling_missing_days(): void
    {
        $configuration = GoogleAdsImportConfiguration::query()->create($this->configurationAttributes(['date_range_days' => 3]));
        GoogleAdsCampaignDailyMetric::query()->create($this->metricAttributes(['campaign_id' => '1001', 'currency_code' => 'RON']));
        GoogleAdsCampaignDailyMetric::query()->create($this->metricAttributes(['campaign_id' => '1002', 'currency_code' => 'EUR']));

        $health = app(GoogleAdsCampaignMetricsHealthService::class)->inspect(
            $configuration,
            CarbonImmutable::parse('2026-11-01 08:00:00', 'UTC'),
        );

        $this->assertSame('invalid_data', $health['status']);
        $this->assertSame(2, $health['currency_count']);
        $this->assertSame(0, GoogleAdsCampaignDailyMetric::query()->where('impressions', 0)->count());
    }

    /** @return array<string, mixed> */
    private function configurationAttributes(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Test Google Ads account',
            'customer_id' => '1234567890',
            'account_timezone' => 'UTC',
            'selected_campaign_ids' => [],
            'date_range_days' => 30,
            'metric_mapping' => GoogleAdsImportConfiguration::METRIC_MAPPING,
            'currency_code' => 'RON',
            'credential_reference' => 'env:GOOGLE_ADS',
            'is_active' => true,
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function row(array $overrides = []): array
    {
        return array_replace([
            'account_customer_id' => '1234567890',
            'campaign_id' => '1001',
            'campaign_name' => 'Campaign One',
            'metric_date' => '2026-10-01',
            'impressions' => '100',
            'clicks' => '10',
            'cost_micros' => '100000',
            'conversions' => '2.000000',
            'currency_code' => 'RON',
            'api_version' => 'v26',
            'imported_at' => '2026-10-02 12:00:00',
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function metricAttributes(array $overrides = []): array
    {
        return array_replace([
            'account_customer_id' => '1234567890',
            'campaign_id' => '1001',
            'campaign_name' => 'Campaign One',
            'metric_date' => '2026-10-01',
            'impressions' => 100,
            'clicks' => 10,
            'cost_micros' => 100000,
            'conversions' => '2.000000',
            'currency_code' => 'RON',
            'api_version' => 'v26',
            'source_name' => 'google_ads',
            'source_version' => 'v26',
            'imported_at' => '2026-10-02 12:00:00',
        ], $overrides);
    }
}
