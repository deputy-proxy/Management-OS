<?php

namespace Tests\Feature;

use App\Contracts\Advertising\GoogleAdsCampaignMetricsAdapter;
use App\Filament\Resources\GoogleAdsImportConfigurations\GoogleAdsImportConfigurationResource;
use App\Models\GoogleAdsCampaignDailyMetric;
use App\Models\GoogleAdsImportConfiguration;
use App\Services\GoogleAds\GoogleAdsAdapterException;
use App\Services\GoogleAds\MockGoogleAdsCampaignMetricsAdapter;
use App\Services\GoogleAdsCampaignMetricsImporter;
use Carbon\CarbonImmutable;
use Filament\Schemas\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\TestCase;

class GoogleAdsImportConfigurationTest extends TestCase
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
            'services.google_ads.max_range_days' => 90,
        ]);
    }

    public function test_configuration_persists_account_campaign_period_currency_and_canonical_mapping(): void
    {
        $configuration = GoogleAdsImportConfiguration::query()->create($this->attributes([
            'selected_campaign_ids' => ['1001', '1002'],
            'date_range_start' => '2026-10-01',
            'date_range_end' => '2026-10-07',
            'currency_code' => 'ron',
            'credential_reference' => 'env:GOOGLE_ADS',
        ]));

        $this->assertSame('1234567890', $configuration->customer_id);
        $this->assertSame('9998887777', $configuration->login_customer_id);
        $this->assertSame(['1001', '1002'], $configuration->selected_campaign_ids);
        $this->assertSame('RON', $configuration->currency_code);
        $this->assertSame(GoogleAdsImportConfiguration::METRIC_MAPPING, $configuration->metric_mapping);
        $this->assertSame(['2026-10-01', '2026-10-07'], $configuration->defaultPeriod(CarbonImmutable::parse('2026-10-08', 'Europe/Bucharest')));
        $this->assertSame('ready', $configuration->configurationStatus());
    }

    public function test_rolling_default_period_uses_the_ads_account_local_date_without_utc_assumptions(): void
    {
        $configuration = GoogleAdsImportConfiguration::query()->create($this->attributes([
            'date_range_days' => 7,
        ]));

        $period = $configuration->defaultPeriod(CarbonImmutable::parse('2026-11-01 00:15:00', 'Pacific/Auckland'));

        $this->assertSame(['2026-10-25', '2026-10-31'], $period);
    }

    public function test_configuration_rejects_invalid_accounts_campaigns_currencies_and_credential_references(): void
    {
        $cases = [
            ['customer_id' => '1234'],
            ['login_customer_id' => 'not-an-account'],
            ['selected_campaign_ids' => ['abc']],
            ['selected_campaign_ids' => ['1001', '1001']],
            ['currency_code' => 'EURO'],
            ['credential_reference' => 'client-secret-value'],
            ['metric_mapping' => ['impressions' => 'arbitrary.provider.path']],
            ['date_range_start' => '2026-10-02', 'date_range_end' => '2026-10-01'],
            ['date_range_start' => '2026-10-01'],
            ['date_range_days' => 91],
        ];

        foreach ($cases as $index => $overrides) {
            try {
                GoogleAdsImportConfiguration::query()->create($this->attributes(array_merge(['name' => 'Invalid '.$index], $overrides)));
                $this->fail('Invalid Google Ads configuration must be rejected.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_secret_values_have_no_configuration_table_columns_and_are_never_mass_assignable(): void
    {
        $columns = SchemaFacade::getColumnListing('google_ads_import_configurations');
        foreach (['client_secret', 'refresh_token', 'access_token', 'oauth_token'] as $secretColumn) {
            $this->assertNotContains($secretColumn, $columns);
        }

        $configuration = new GoogleAdsImportConfiguration(array_merge($this->attributes(), [
            'client_secret' => 'do-not-store',
            'refresh_token' => 'do-not-store',
        ]));
        $this->assertArrayNotHasKey('client_secret', $configuration->getAttributes());
        $this->assertArrayNotHasKey('refresh_token', $configuration->getAttributes());
    }

    public function test_configuration_has_explicit_not_configured_and_disabled_states(): void
    {
        $configuration = GoogleAdsImportConfiguration::query()->create($this->attributes([
            'credential_reference' => null,
        ]));
        $this->assertSame('not_configured', $configuration->configurationStatus());

        $configuration->update(['is_active' => false]);
        $this->assertSame('disabled', $configuration->configurationStatus());

        $configuration->update(['is_active' => true, 'credential_reference' => 'env:GOOGLE_ADS']);
        config(['services.google_ads.refresh_token' => null]);
        $this->assertSame('not_configured', $configuration->configurationStatus());
    }

    public function test_importer_uses_mock_adapter_and_applies_account_campaign_date_and_currency_scope(): void
    {
        $configuration = GoogleAdsImportConfiguration::query()->create($this->attributes([
            'selected_campaign_ids' => ['1001'],
            'currency_code' => 'RON',
        ]));
        $rows = [
            $this->row('1001', '2026-10-01', 'RON'),
            $this->row('1002', '2026-10-01', 'RON'),
            $this->row('1001', '2026-10-02', 'RON'),
        ];
        $this->app->instance(GoogleAdsCampaignMetricsAdapter::class, new MockGoogleAdsCampaignMetricsAdapter($rows));

        $result = app(GoogleAdsCampaignMetricsImporter::class)->import('2026-10-01', '2026-10-01', false, $configuration);

        $this->assertSame(1, $result['row_count']);
        $this->assertSame(1, GoogleAdsCampaignDailyMetric::query()->count());
        $this->assertSame('1001', GoogleAdsCampaignDailyMetric::query()->value('campaign_id'));
    }

    public function test_rest_adapter_uses_the_saved_campaign_selection_and_manager_account(): void
    {
        $configuration = GoogleAdsImportConfiguration::query()->create($this->attributes([
            'selected_campaign_ids' => ['1001', '1002'],
            'login_customer_id' => '111-222-3333',
        ]));
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-access-token'], 200),
            'https://googleads.googleapis.com/*' => Http::response(['results' => [[
                'campaign' => ['id' => '1001', 'name' => 'Selected campaign'],
                'segments' => ['date' => '2026-10-01'],
                'metrics' => ['impressions' => '10', 'clicks' => '2', 'costMicros' => '1000', 'conversions' => '1'],
                'customer' => ['currencyCode' => 'RON'],
            ]]], 200),
        ]);

        $result = app(GoogleAdsCampaignMetricsImporter::class)->import('2026-10-01', '2026-10-01', true, $configuration);

        $this->assertSame(1, $result['row_count']);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'googleads.googleapis.com')
            && str_contains((string) $request->data()['query'], 'campaign.id IN (1001, 1002)')
            && $request->hasHeader('login-customer-id', '1112223333'));
        $this->assertDatabaseCount('google_ads_campaign_daily_metrics', 0);
    }

    public function test_import_command_accepts_a_saved_configuration_without_exposing_secrets(): void
    {
        $configuration = GoogleAdsImportConfiguration::query()->create($this->attributes([
            'selected_campaign_ids' => ['1001'],
            'credential_reference' => 'env:GOOGLE_ADS',
        ]));
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-access-token'], 200),
            'https://googleads.googleapis.com/*' => Http::response(['results' => [[
                'campaign' => ['id' => '1001', 'name' => 'Selected campaign'],
                'segments' => ['date' => '2026-10-01'],
                'metrics' => ['impressions' => '10', 'clicks' => '2', 'costMicros' => '1000', 'conversions' => '1'],
                'customer' => ['currencyCode' => 'RON'],
            ]]], 200),
        ]);

        $exitCode = Artisan::call('market:import-google-ads', [
            '--from' => '2026-10-01',
            '--to' => '2026-10-01',
            '--configuration' => $configuration->getKey(),
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Validated 1 campaign-day rows', Artisan::output());
        $this->assertSame('ready', $configuration->fresh()->configurationStatus());
        $this->assertNotNull($configuration->fresh()->last_checked_at);
        $this->assertDatabaseCount('google_ads_campaign_daily_metrics', 0);
    }

    public function test_importer_rejects_malformed_normalized_rows_before_any_write(): void
    {
        $configuration = GoogleAdsImportConfiguration::query()->create($this->attributes());
        $malformed = $this->row('1001', '2026-10-01', 'RON');
        $malformed['impressions'] = '-1';
        $this->app->instance(GoogleAdsCampaignMetricsAdapter::class, new MockGoogleAdsCampaignMetricsAdapter([$malformed]));

        $this->expectException(\RuntimeException::class);
        try {
            app(GoogleAdsCampaignMetricsImporter::class)->import('2026-10-01', '2026-10-01', false, $configuration);
        } finally {
            $this->assertDatabaseCount('google_ads_campaign_daily_metrics', 0);
        }
    }

    public function test_importer_reports_not_configured_before_calling_adapter(): void
    {
        $configuration = GoogleAdsImportConfiguration::query()->create($this->attributes(['credential_reference' => null]));
        Http::fake();

        try {
            app(GoogleAdsCampaignMetricsImporter::class)->import('2026-10-01', '2026-10-01', false, $configuration);
            $this->fail('Missing credential configuration must be rejected.');
        } catch (ValidationException) {
            Http::assertNothingSent();
        }
    }

    public function test_disabled_configuration_is_not_used_for_imports(): void
    {
        $configuration = GoogleAdsImportConfiguration::query()->create($this->attributes(['is_active' => false]));
        Http::fake();

        try {
            app(GoogleAdsCampaignMetricsImporter::class)->import('2026-10-01', '2026-10-01', false, $configuration);
            $this->fail('Disabled configurations must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('disabled', collect($exception->errors())->flatten()->implode(' '));
            Http::assertNothingSent();
        }
    }

    public function test_provider_errors_have_safe_codes_and_retryability_without_response_body(): void
    {
        $configuration = GoogleAdsImportConfiguration::query()->create($this->attributes());
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-access-token'], 200),
            'https://googleads.googleapis.com/*' => Http::response(['sensitive' => 'must-not-appear'], 503, ['request-id' => 'request-123']),
        ]);

        try {
            app(GoogleAdsCampaignMetricsImporter::class)->import('2026-10-01', '2026-10-01', false, $configuration);
            $this->fail('Provider failures must be surfaced.');
        } catch (GoogleAdsAdapterException $exception) {
            $this->assertSame('provider_http_503', $exception->errorCode);
            $this->assertTrue($exception->retryable);
            $this->assertStringContainsString('request-123', $exception->getMessage());
            $this->assertStringNotContainsString('must-not-appear', $exception->getMessage());
            $this->assertSame('error', $configuration->fresh()->configurationStatus());
            $this->assertSame('provider_http_503', $configuration->fresh()->last_error_code);
            $this->assertNotNull($configuration->fresh()->last_checked_at);
        }
    }

    public function test_importer_rejects_fixed_date_range_overflow_and_mock_currency_mismatch(): void
    {
        $configuration = GoogleAdsImportConfiguration::query()->create($this->attributes([
            'date_range_start' => '2026-10-01',
            'date_range_end' => '2026-10-01',
            'currency_code' => 'EUR',
        ]));
        $this->app->instance(GoogleAdsCampaignMetricsAdapter::class, new MockGoogleAdsCampaignMetricsAdapter([
            $this->row('1001', '2026-10-01', 'RON'),
        ]));

        try {
            app(GoogleAdsCampaignMetricsImporter::class)->import('2026-10-02', '2026-10-02', false, $configuration);
            $this->fail('A date outside the configured fixed period must be rejected.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('google_ads_campaign_daily_metrics', 0);
        }

        $this->expectException(GoogleAdsAdapterException::class);
        app(GoogleAdsCampaignMetricsImporter::class)->import('2026-10-01', '2026-10-01', false, $configuration);
    }

    public function test_filament_configuration_resource_form_can_be_constructed(): void
    {
        $this->assertInstanceOf(Schema::class, GoogleAdsImportConfigurationResource::form(Schema::make()));
    }

    /** @return array<string, mixed> */
    private function attributes(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Primary Ads account',
            'customer_id' => '123-456-7890',
            'login_customer_id' => '999-888-7777',
            'selected_campaign_ids' => [],
            'date_range_days' => 30,
            'metric_mapping' => GoogleAdsImportConfiguration::METRIC_MAPPING,
            'currency_code' => null,
            'credential_reference' => 'env:GOOGLE_ADS',
            'is_active' => true,
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function row(string $campaignId, string $date, string $currency): array
    {
        return [
            'account_customer_id' => '1234567890',
            'campaign_id' => $campaignId,
            'campaign_name' => 'Campaign '.$campaignId,
            'metric_date' => $date,
            'impressions' => '10',
            'clicks' => '2',
            'cost_micros' => '1000',
            'conversions' => '1.000000',
            'currency_code' => $currency,
            'api_version' => 'v26',
            'imported_at' => now(),
        ];
    }
}
