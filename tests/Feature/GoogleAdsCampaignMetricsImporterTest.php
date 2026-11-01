<?php

namespace Tests\Feature;

use App\Contracts\Advertising\GoogleAdsCampaignMetricsAdapter;
use App\Models\GoogleAdsCampaignDailyMetric;
use App\Models\GoogleAdsCampaignDailyMetricRevision;
use App\Models\GoogleAdsImportBatch;
use App\Models\GoogleAdsImportConfiguration;
use App\Services\GoogleAds\MockGoogleAdsCampaignMetricsAdapter;
use App\Services\GoogleAdsCampaignMetricsImporter;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class GoogleAdsCampaignMetricsImporterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.google_ads.client_id' => 'client-id',
            'services.google_ads.client_secret' => 'client-secret',
            'services.google_ads.refresh_token' => 'refresh-token',
            'services.google_ads.customer_id' => '123-456-7890',
            'services.google_ads.login_customer_id' => '999-888-7777',
            'services.google_ads.api_version' => 'v26',
            'services.google_ads.max_range_days' => 90,
        ]);
    }

    public function test_import_paginates_and_upserts_campaign_day_rows_idempotently(): void
    {
        $adsCalls = 0;
        Http::fake(function ($request) use (&$adsCalls) {
            if ($request->url() === 'https://oauth2.googleapis.com/token') {
                return Http::response(['access_token' => 'access-token'], 200);
            }
            $adsCalls++;

            return match ($adsCalls) {
                1 => Http::response(['results' => [$this->row('1001', 'Campaign One', '2026-10-01', 120, 12, 1234567, 2.5, 'RON')], 'nextPageToken' => 'next-page'], 200),
                2 => Http::response(['results' => [$this->row('1002', 'Campaign Two', '2026-10-01', 80, 8, 765432, 1, 'RON')]], 200),
                3 => Http::response(['results' => [$this->row('1001', 'Campaign One renamed', '2026-10-01', 121, 13, 2234567, 3, 'RON')]], 200),
                default => Http::response(['results' => []], 200),
            };
        });
        $importer = app(GoogleAdsCampaignMetricsImporter::class);
        $first = $importer->import('2026-10-01', '2026-10-02');
        $this->assertSame(2, $first['row_count']);
        $this->assertSame(2, GoogleAdsCampaignDailyMetric::query()->count());

        $second = $importer->import('2026-10-01', '2026-10-02');

        $this->assertSame(1, $second['row_count']);
        $this->assertSame(2, GoogleAdsCampaignDailyMetric::query()->count());
        $this->assertSame('Campaign One renamed', GoogleAdsCampaignDailyMetric::query()->where('campaign_id', '1001')->value('campaign_name'));
        $this->assertSame('RON', GoogleAdsCampaignDailyMetric::query()->where('campaign_id', '1001')->value('currency_code'));
        $this->assertSame('3.000000', GoogleAdsCampaignDailyMetric::query()->where('campaign_id', '1001')->value('conversions'));
        Http::assertSent(fn ($request): bool => $request->hasHeader('login-customer-id', '9998887777') && $request->hasHeader('Authorization', 'Bearer access-token'));
    }

    public function test_provider_rows_outside_the_requested_period_are_rejected_without_partial_writes(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-token'], 200),
            'https://googleads.googleapis.com/*' => Http::response(['results' => [
                $this->row('1001', 'Campaign One', '2026-10-02', 1, 1, 100, 0, 'RON'),
            ]], 200),
        ]);

        try {
            app(GoogleAdsCampaignMetricsImporter::class)->import('2026-10-01', '2026-10-01');
            $this->fail('Out-of-range provider dates must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('outside the requested period', $exception->getMessage());
            $this->assertDatabaseCount('google_ads_campaign_daily_metrics', 0);
        }
    }

    public function test_daily_metric_model_validates_identity_counters_currency_and_date(): void
    {
        $attributes = $this->modelAttributes();
        $metric = GoogleAdsCampaignDailyMetric::query()->create($attributes);
        $this->assertSame('1234567890', $metric->account_customer_id);
        $this->assertSame('RON', $metric->currency_code);
        $this->assertInstanceOf(CarbonImmutable::class, $metric->imported_at);

        foreach ([
            ['clicks' => -1],
            ['conversions' => 1000000000000],
            ['currency_code' => 'EURO'],
            ['campaign_id' => 'not-numeric'],
            ['api_version' => 'latest'],
            ['metric_date' => '2026-12-01'],
        ] as $overrides) {
            try {
                GoogleAdsCampaignDailyMetric::query()->create(array_replace($this->modelAttributes(), $overrides));
                $this->fail('Invalid daily metrics must be rejected.');
            } catch (\InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_daily_metric_accepts_the_maximum_six_decimal_conversion_value(): void
    {
        $metric = GoogleAdsCampaignDailyMetric::query()->create(array_replace(
            $this->modelAttributes(),
            ['conversions' => '999999999999.999999'],
        ));

        $this->assertSame('999999999999.999999', $metric->conversions);
    }

    public function test_daily_metric_query_scopes_normalize_account_and_validate_period(): void
    {
        GoogleAdsCampaignDailyMetric::query()->create($this->modelAttributes());

        $this->assertSame(1, GoogleAdsCampaignDailyMetric::query()->forAccount('123-456-7890')->withinPeriod('2026-10-01', '2026-10-01')->count());
        try {
            GoogleAdsCampaignDailyMetric::query()->withinPeriod('2026-10-02', '2026-10-01')->count();
            $this->fail('Reversed periods must be rejected.');
        } catch (\InvalidArgumentException) {
            $this->assertTrue(true);
        }

        $this->expectException(\InvalidArgumentException::class);
        GoogleAdsCampaignDailyMetric::query()->withinPeriod('not-a-date', '2026-10-01')->count();
    }

    public function test_daily_metric_identity_is_unique_per_account_campaign_and_day(): void
    {
        $attributes = $this->modelAttributes();
        GoogleAdsCampaignDailyMetric::query()->create($attributes);

        $this->expectException(UniqueConstraintViolationException::class);
        GoogleAdsCampaignDailyMetric::query()->create($attributes);
    }

    public function test_provider_counter_values_above_database_range_are_rejected(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-token'], 200),
            'https://googleads.googleapis.com/*' => Http::response(['results' => [
                $this->row('1001', 'Campaign One', '2026-10-01', 1, 1, 9223372036854775808, 0, 'RON'),
            ]], 200),
        ]);

        $this->expectException(RuntimeException::class);
        app(GoogleAdsCampaignMetricsImporter::class)->import('2026-10-01', '2026-10-01');
    }

    public function test_dry_run_validates_rows_without_persisting_them(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-token'], 200),
            'https://googleads.googleapis.com/*' => Http::response(['results' => [$this->row('1001', 'Campaign One', '2026-10-01', 1, 1, 100, 0, 'RON')]], 200),
        ]);

        $result = app(GoogleAdsCampaignMetricsImporter::class)->import('2026-10-01', '2026-10-01', true);

        $this->assertSame(1, $result['row_count']);
        $this->assertTrue($result['dry_run']);
        $this->assertDatabaseCount('google_ads_campaign_daily_metrics', 0);
    }

    public function test_invalid_date_ranges_are_rejected_before_network_requests(): void
    {
        Http::fake();
        try {
            app(GoogleAdsCampaignMetricsImporter::class)->import('2026-10-02', '2026-10-01');
            $this->fail('Invalid date range must be rejected.');
        } catch (ValidationException) {
            Http::assertNothingSent();
        }
    }

    public function test_inclusive_date_range_cannot_exceed_configured_limit(): void
    {
        config(['services.google_ads.max_range_days' => 1]);
        Http::fake();

        try {
            app(GoogleAdsCampaignMetricsImporter::class)->import('2026-10-01', '2026-10-02');
            $this->fail('A two-day range must be rejected when the limit is one day.');
        } catch (ValidationException) {
            Http::assertNothingSent();
        }
    }

    public function test_malformed_provider_rows_fail_without_partial_persistence(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-token'], 200),
            'https://googleads.googleapis.com/*' => Http::response(['results' => [['campaign' => ['id' => 'bad'], 'metrics' => []]]], 200),
        ]);

        try {
            app(GoogleAdsCampaignMetricsImporter::class)->import('2026-10-01', '2026-10-01');
            $this->fail('Malformed rows must be rejected.');
        } catch (RuntimeException) {
            $this->assertDatabaseCount('google_ads_campaign_daily_metrics', 0);
            $this->assertDatabaseHas('google_ads_import_batches', ['status' => 'failed']);
        }
    }

    public function test_replayed_identical_batches_are_audited_without_creating_false_revisions(): void
    {
        $row = $this->normalizedRow();
        app()->instance(GoogleAdsCampaignMetricsAdapter::class, new MockGoogleAdsCampaignMetricsAdapter([$row]));
        $importer = app(GoogleAdsCampaignMetricsImporter::class);

        $first = $importer->import('2026-10-01', '2026-10-01');
        $second = $importer->import('2026-10-01', '2026-10-01');

        $this->assertNotSame($first['batch_id'], $second['batch_id']);
        $this->assertDatabaseCount('google_ads_import_batches', 2);
        $this->assertDatabaseCount('google_ads_campaign_daily_metrics', 1);
        $this->assertDatabaseCount('google_ads_campaign_daily_metric_revisions', 0);
        $metric = GoogleAdsCampaignDailyMetric::query()->firstOrFail();
        $this->assertSame($second['batch_id'], $metric->import_batch_id);
        $this->assertSame('google_ads', $metric->source_name);
        $this->assertSame('v26', $metric->source_version);
        $this->assertSame('completed', GoogleAdsImportBatch::query()->findOrFail($second['batch_id'])->status);
    }

    public function test_provider_corrections_preserve_the_previous_metric_revision(): void
    {
        app()->instance(GoogleAdsCampaignMetricsAdapter::class, new MockGoogleAdsCampaignMetricsAdapter([$this->normalizedRow()]));
        $importer = app(GoogleAdsCampaignMetricsImporter::class);
        $importer->import('2026-10-01', '2026-10-01');

        $corrected = $this->normalizedRow(['impressions' => '125', 'clicks' => '15', 'cost_micros' => '250000', 'conversions' => '3.250000']);
        app()->instance(GoogleAdsCampaignMetricsAdapter::class, new MockGoogleAdsCampaignMetricsAdapter([$corrected]));
        $result = app(GoogleAdsCampaignMetricsImporter::class)->import('2026-10-01', '2026-10-01');

        $metric = GoogleAdsCampaignDailyMetric::query()->firstOrFail();
        $revision = GoogleAdsCampaignDailyMetricRevision::query()->firstOrFail();
        $this->assertSame('125', (string) $metric->impressions);
        $this->assertSame('100', $revision->previous_values['impressions']);
        $this->assertSame('10', $revision->previous_values['clicks']);
        $this->assertSame($result['batch_id'], $revision->import_batch_id);
        $this->assertSame($result['batch_id'], $metric->import_batch_id);
    }

    public function test_empty_source_result_records_a_successful_batch_without_deleting_missing_campaign_metrics(): void
    {
        GoogleAdsCampaignDailyMetric::query()->create($this->modelAttributes());
        app()->instance(GoogleAdsCampaignMetricsAdapter::class, new MockGoogleAdsCampaignMetricsAdapter([]));

        $result = app(GoogleAdsCampaignMetricsImporter::class)->import('2026-10-01', '2026-10-01');

        $this->assertSame(0, $result['row_count']);
        $this->assertDatabaseCount('google_ads_campaign_daily_metrics', 1);
        $this->assertDatabaseHas('google_ads_import_batches', [
            'id' => $result['batch_id'], 'status' => 'completed', 'row_count' => 0,
        ]);
    }

    public function test_duplicate_campaign_day_rows_fail_the_batch_before_any_metric_writes(): void
    {
        $row = $this->normalizedRow();
        app()->instance(GoogleAdsCampaignMetricsAdapter::class, new MockGoogleAdsCampaignMetricsAdapter([$row, $row]));

        try {
            app(GoogleAdsCampaignMetricsImporter::class)->import('2026-10-01', '2026-10-01');
            $this->fail('Duplicate natural keys must fail the batch.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('duplicate campaign-day', $exception->getMessage());
            $this->assertDatabaseCount('google_ads_campaign_daily_metrics', 0);
            $this->assertDatabaseHas('google_ads_import_batches', ['status' => 'failed', 'error_code' => 'invalid_import_payload']);
        }
    }

    public function test_malformed_later_row_cannot_partially_apply_an_earlier_correction(): void
    {
        GoogleAdsCampaignDailyMetric::query()->create($this->modelAttributes());
        $corrected = $this->normalizedRow(['impressions' => '999']);
        $malformed = $this->normalizedRow(['campaign_id' => 'bad-id']);
        app()->instance(GoogleAdsCampaignMetricsAdapter::class, new MockGoogleAdsCampaignMetricsAdapter([$corrected, $malformed]));

        try {
            app(GoogleAdsCampaignMetricsImporter::class)->import('2026-10-01', '2026-10-01');
            $this->fail('A malformed row must reject the complete import batch.');
        } catch (RuntimeException) {
            $this->assertSame('100', (string) GoogleAdsCampaignDailyMetric::query()->value('impressions'));
            $this->assertDatabaseCount('google_ads_campaign_daily_metric_revisions', 0);
            $this->assertDatabaseHas('google_ads_import_batches', ['status' => 'failed']);
        }
    }

    public function test_currency_mismatch_fails_batch_without_changing_existing_data(): void
    {
        GoogleAdsCampaignDailyMetric::query()->create($this->modelAttributes());
        $row = $this->normalizedRow(['currency_code' => 'USD']);
        app()->instance(GoogleAdsCampaignMetricsAdapter::class, new MockGoogleAdsCampaignMetricsAdapter([$row]));

        try {
            app(GoogleAdsCampaignMetricsImporter::class)->import('2026-10-01', '2026-10-01', false, $this->configuredImport());
            $this->fail('A currency mismatch must fail the import.');
        } catch (RuntimeException) {
            $this->assertSame('RON', GoogleAdsCampaignDailyMetric::query()->value('currency_code'));
            $this->assertDatabaseCount('google_ads_campaign_daily_metric_revisions', 0);
            $this->assertDatabaseHas('google_ads_import_batches', ['status' => 'failed']);
        }
    }

    private function configuredImport(): GoogleAdsImportConfiguration
    {
        return new GoogleAdsImportConfiguration([
            'name' => 'Test configuration',
            'customer_id' => '1234567890',
            'login_customer_id' => null,
            'selected_campaign_ids' => [],
            'date_range_days' => 90,
            'metric_mapping' => GoogleAdsImportConfiguration::METRIC_MAPPING,
            'currency_code' => 'RON',
            'credential_reference' => 'env:GOOGLE_ADS',
            'is_active' => true,
        ]);
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function normalizedRow(array $overrides = []): array
    {
        return array_replace([
            'account_customer_id' => '1234567890',
            'campaign_id' => '1001',
            'campaign_name' => 'Campaign One',
            'metric_date' => '2026-10-01',
            'impressions' => '100',
            'clicks' => '10',
            'cost_micros' => '100000',
            'conversions' => '2.500000',
            'currency_code' => 'RON',
            'api_version' => 'v26',
            'imported_at' => '2026-10-02 12:00:00',
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function modelAttributes(): array
    {
        return [
            'account_customer_id' => '123-456-7890', 'campaign_id' => '1001',
            'campaign_name' => 'Campaign One', 'metric_date' => '2026-10-01',
            'impressions' => 100, 'clicks' => 10, 'cost_micros' => 100000,
            'conversions' => '2.500000', 'currency_code' => 'ron',
            'api_version' => 'v26', 'imported_at' => '2026-10-02 12:00:00',
        ];
    }

    /** @return array<string, mixed> */
    private function row(string $id, string $name, string $date, int $impressions, int $clicks, int|float|string $costMicros, float $conversions, string $currency): array
    {
        return [
            'campaign' => ['id' => $id, 'name' => $name],
            'segments' => ['date' => $date],
            'metrics' => ['impressions' => (string) $impressions, 'clicks' => (string) $clicks, 'costMicros' => (string) $costMicros, 'conversions' => (string) $conversions],
            'customer' => ['currencyCode' => $currency],
        ];
    }
}
