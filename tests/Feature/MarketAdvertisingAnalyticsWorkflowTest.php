<?php

namespace Tests\Feature;

use App\Contracts\Advertising\GoogleAdsCampaignMetricsAdapter;
use App\Data\MarketIntelligence\MarketDataImportRequest;
use App\Filament\Pages\MarketAnalysis;
use App\Models\GoogleAdsCampaignDailyMetric;
use App\Models\GoogleAdsImportConfiguration;
use App\Models\User;
use App\Services\GoogleAds\MockGoogleAdsCampaignMetricsAdapter;
use App\Services\GoogleAdsCampaignMetricsImporter;
use App\Services\MarketAnalysisDashboardData;
use App\Services\MarketIntelligence\MarketDataImportService;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketAdvertisingAnalyticsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_synthetic_imports_reconcile_to_market_and_advertising_dashboard_totals(): void
    {
        config([
            'services.google_ads.client_id' => 'synthetic-client',
            'services.google_ads.client_secret' => 'synthetic-secret',
            'services.google_ads.refresh_token' => 'synthetic-refresh-token',
            'services.google_ads.api_version' => 'v26',
        ]);

        $marketRequest = new MarketDataImportRequest(manualRecords: [$this->marketRecord()]);
        $marketImporter = app(MarketDataImportService::class);
        $marketRun = $marketImporter->import('manual', $marketRequest);
        $marketReplay = $marketImporter->import('manual', $marketRequest);

        $this->assertSame('succeeded', $marketRun->status);
        $this->assertSame(1, $marketRun->records_imported);
        $this->assertSame('succeeded', $marketReplay->status);
        $this->assertSame(0, $marketReplay->records_imported);
        $this->assertSame(1, $marketReplay->records_skipped);
        $this->assertDatabaseCount('market_trends', 1);

        $configuration = GoogleAdsImportConfiguration::query()->create([
            'name' => 'Synthetic analytics account',
            'customer_id' => '1234567890',
            'account_timezone' => 'UTC',
            'selected_campaign_ids' => [],
            'date_range_days' => 30,
            'metric_mapping' => GoogleAdsImportConfiguration::METRIC_MAPPING,
            'currency_code' => null,
            'credential_reference' => 'env:GOOGLE_ADS',
            'is_active' => true,
        ]);
        app()->instance(GoogleAdsCampaignMetricsAdapter::class, new MockGoogleAdsCampaignMetricsAdapter([
            $this->adsRow('1001', 'RON campaign', '2026-10-10', '1500000', '10', '100', '2.000000', 'RON'),
            $this->adsRow('1002', 'EUR campaign', '2026-10-11', '2500000', '20', '200', '1.000000', 'EUR'),
        ]));

        $adsResult = app(GoogleAdsCampaignMetricsImporter::class)->import(
            '2026-10-10',
            '2026-10-11',
            false,
            $configuration,
        );

        $this->assertSame('completed', $adsResult['status']);
        $this->assertSame(2, $adsResult['row_count']);
        $this->assertSame(2, $adsResult['rows_inserted']);
        $this->assertDatabaseCount('google_ads_campaign_daily_metrics', 2);

        $summary = app(MarketAnalysisDashboardData::class)->summary([
            'market_trend.index' => true,
            'google_ads_metrics.view' => true,
        ], CarbonImmutable::parse('2026-10-18 12:00:00', 'UTC'));

        $this->assertSame(1, $summary['market_trends']['count']);
        $this->assertSame('125.000000', $summary['market_trends']['recent'][0]['value']);
        $this->assertSame('manual', $summary['market_trends']['recent'][0]['source_type']);
        $this->assertSame('fixture:market-demand:2026-10', $summary['market_trends']['recent'][0]['source_reference']);

        $this->assertSame(2, $summary['google_ads']['row_count']);
        $this->assertSame(2, $summary['google_ads']['campaign_count']);
        $totals = collect($summary['google_ads']['currency_totals'])->keyBy('currency_code');
        $this->assertSame('1500000', $totals['RON']['cost_micros']);
        $this->assertSame(100, $totals['RON']['impressions']);
        $this->assertSame('2.000000', $totals['RON']['conversions']);
        $this->assertSame('2500000', $totals['EUR']['cost_micros']);
        $this->assertSame(200, $totals['EUR']['impressions']);
        $this->assertSame('1.000000', $totals['EUR']['conversions']);
    }

    public function test_google_ads_dashboard_window_includes_day_29_and_today_but_excludes_stale_and_future_rows(): void
    {
        $today = CarbonImmutable::parse('2026-10-18 12:00:00', 'UTC');
        foreach ([
            ['campaign_id' => '1001', 'metric_date' => '2026-09-18', 'impressions' => 10],
            ['campaign_id' => '1002', 'metric_date' => '2026-09-19', 'impressions' => 20],
            ['campaign_id' => '1003', 'metric_date' => '2026-10-18', 'impressions' => 30],
            ['campaign_id' => '1004', 'metric_date' => '2026-10-19', 'impressions' => 40],
        ] as $row) {
            GoogleAdsCampaignDailyMetric::query()->create(array_replace($this->metricAttributes(), $row));
        }

        $summary = app(MarketAnalysisDashboardData::class)->summary(
            ['google_ads_metrics.view' => true],
            $today,
        );

        $this->assertSame('Sep 19, 2026', $summary['freshness_start']);
        $this->assertSame(2, $summary['google_ads']['row_count']);
        $this->assertSame(50, $summary['google_ads']['currency_totals'][0]['impressions']);
    }

    public function test_empty_advertising_dataset_is_explicit_and_permission_gated(): void
    {
        $visible = app(MarketAnalysisDashboardData::class)->summary(['google_ads_metrics.view' => true]);
        $this->assertSame(0, $visible['google_ads']['row_count']);
        $this->assertSame(0, $visible['google_ads']['campaign_count']);
        $this->assertSame([], $visible['google_ads']['currency_totals']);
        $this->assertNull($visible['google_ads']['latest_imported_at']);

        $restricted = app(MarketAnalysisDashboardData::class)->summary(['google_ads_metrics.view' => false]);
        $this->assertFalse($restricted['google_ads']['can_view']);
        $this->assertNull($restricted['google_ads']['row_count']);
        $this->assertSame([], $restricted['google_ads']['currency_totals']);
    }

    public function test_dashboard_renders_empty_and_populated_advertising_states_for_authorized_users(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $user = User::factory()->create();
        $roleClass = config('hexa.models.role');
        $role = $roleClass::create([
            'name' => 'Synthetic advertising dashboard viewer '.uniqid(),
            'created_by_name' => 'Test',
            'access' => ['Marketing' => ['market-analysis.view', 'paid_campaign.index']],
            'guard' => 'web',
        ]);
        $user->roles()->attach($role->getKey());

        $this->actingAs($user)->get(MarketAnalysis::getUrl())
            ->assertSuccessful()
            ->assertSee('Google Ads campaign performance')
            ->assertSee('No imported campaign metrics are available for this period.');

        GoogleAdsCampaignDailyMetric::query()->create($this->metricAttributes());

        $this->actingAs($user)->get(MarketAnalysis::getUrl())
            ->assertSuccessful()
            ->assertSee('Freshness fixture')
            ->assertSee('RON')
            ->assertSee('Campaign-day rows');
    }

    /** @return array<string, mixed> */
    private function marketRecord(): array
    {
        return [
            'idempotency_key' => 'market-demand:2026-10',
            'source_reference' => 'fixture:market-demand:2026-10',
            'metric_key' => 'search_interest_index',
            'name' => 'Synthetic market demand',
            'category' => 'search_interest',
            'market' => 'RO',
            'segment' => 'adult-online',
            'unit' => 'index',
            'value' => '125',
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-31',
            'observed_at' => '2026-11-01T08:00:00Z',
            'methodology' => 'Deterministic test fixture.',
        ];
    }

    /** @return array<string, mixed> */
    private function adsRow(
        string $campaignId,
        string $campaignName,
        string $date,
        string $costMicros,
        string $clicks,
        string $impressions,
        string $conversions,
        string $currency,
    ): array {
        return [
            'account_customer_id' => '1234567890',
            'campaign_id' => $campaignId,
            'campaign_name' => $campaignName,
            'metric_date' => $date,
            'impressions' => $impressions,
            'clicks' => $clicks,
            'cost_micros' => $costMicros,
            'conversions' => $conversions,
            'currency_code' => $currency,
            'api_version' => 'v26',
            'imported_at' => $date.' 12:00:00',
        ];
    }

    /** @return array<string, mixed> */
    private function metricAttributes(): array
    {
        return [
            'account_customer_id' => '1234567890',
            'campaign_id' => '9999',
            'campaign_name' => 'Freshness fixture',
            'metric_date' => '2026-10-18',
            'impressions' => 1,
            'clicks' => 1,
            'cost_micros' => 100,
            'conversions' => '1.000000',
            'currency_code' => 'RON',
            'api_version' => 'v26',
            'imported_at' => '2026-10-19 12:00:00',
        ];
    }
}
