<?php

namespace Tests\Feature;

use App\Filament\Pages\MarketAnalysis;
use App\Models\Competitor;
use App\Models\CompetitorOffer;
use App\Models\GoogleAdsCampaignDailyMetric;
use App\Models\MarketTrend;
use App\Models\User;
use App\Services\MarketAnalysisDashboardData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class MarketAnalysisDashboardDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_competitor_coverage_offer_inventory_freshness_and_manual_comparisons_are_aggregated_separately(): void
    {
        $today = CarbonImmutable::parse('2026-10-18 12:00:00', config('app.timezone'));
        $alpha = Competitor::factory()->create([
            'name' => 'Alpha', 'site' => 'https://alpha.test', 'instagram' => 'https://instagram.test/alpha',
            'facebook' => '', 'linkedin' => '', 'tiktok' => '',
            'locations' => [['address' => 'First', 'coordinates' => '1,2']],
        ]);
        $beta = Competitor::factory()->create([
            'name' => 'Beta', 'site' => '', 'instagram' => '', 'facebook' => 'https://facebook.test/beta',
            'linkedin' => '', 'tiktok' => '', 'locations' => [],
        ]);
        Competitor::factory()->create([
            'name' => 'Gamma', 'site' => '', 'instagram' => '', 'facebook' => '', 'linkedin' => '', 'tiktok' => '',
            'locations' => [],
        ]);
        Competitor::factory()->create(['name' => 'Deleted competitor'])->delete();

        $this->createOffer($alpha, 'Fresh offer', 'active', 'subscription', $today->toDateString(), 'Similar offer', 'Differentiator');
        $this->createOffer($alpha, 'Stale offer', 'active', 'bundle', $today->subDays(30)->toDateString(), '', 'Different support');
        $this->createOffer($beta, 'Future check offer', 'paused', 'trial', $today->addDay()->toDateString(), 'Similar trial', '');
        $this->createOffer($alpha, 'Deleted offer', 'active', 'subscription', $today->toDateString(), 'Similar offer', 'Differentiator')->delete();

        $summary = app(MarketAnalysisDashboardData::class)->summary($this->allPermissions(), $today);

        $this->assertSame(3, $summary['competitors']['current_count']);
        $this->assertSame(1, $summary['competitors']['soft_deleted_count']);
        $this->assertSame(1, $summary['competitors']['with_locations_count']);
        $this->assertSame(2, $summary['competitors']['without_locations_count']);
        $this->assertSame(1, $this->profileCount($summary, 'Website field populated'));
        $this->assertSame(1, $this->profileCount($summary, 'Instagram field populated'));
        $this->assertSame(1, $this->profileCount($summary, 'Facebook field populated'));

        $this->assertSame(3, $summary['offers']['current_count']);
        $this->assertSame(1, $summary['offers']['soft_deleted_count']);
        $this->assertSame(['active' => 2, 'paused' => 1], $this->bucketCounts($summary['offers']['status_buckets']));
        $this->assertSame(['bundle' => 1, 'subscription' => 1, 'trial' => 1], $this->bucketCounts($summary['offers']['offer_type_buckets']));
        $this->assertSame(1, $summary['offers']['checked_recently_count']);
        $this->assertSame(1, $summary['offers']['stale_count']);
        $this->assertSame(1, $summary['offers']['future_dated_count']);
        $this->assertSame(2, $summary['offers']['with_similar_offer_count']);
        $this->assertSame(2, $summary['offers']['with_differentiator_count']);
        $this->assertSame(1, $summary['offers']['with_both_notes_count']);
        $this->assertSame(1, $summary['offers']['competitors_without_offers_count']);
        $this->assertSame(['Alpha' => 2, 'Beta' => 1, 'Gamma' => 0], $this->competitorOfferCounts($summary['offers']['by_competitor']));
        $this->assertSame('Fresh offer', $summary['offers']['recent_checks'][0]['title']);
        $this->assertSame('Fresh offer', $summary['offers']['qualitative_comparisons'][0]['title']);
        $this->assertArrayNotHasKey('average_offer_price', $summary['offers']);
        $this->assertTrue($summary['market_data']['has_historical_observations']);
        $this->assertSame(4, $summary['offers']['observation_count']);
        $this->assertSame(4, $summary['offers']['observations_with_source_count']);
        $this->assertSame(0, $summary['offers']['observations_with_unknown_source_count']);
        $this->assertCount(4, $summary['offers']['recent_observations']);
        $this->assertSame('October 18, 2026', $summary['as_of']);
        $this->assertSame(config('app.timezone'), $summary['timezone']);
    }

    public function test_google_ads_metrics_are_aggregated_by_currency_and_require_ads_metrics_permission(): void
    {
        $today = CarbonImmutable::parse('2026-10-18 12:00:00', config('app.timezone'));
        foreach ([
            ['campaign_id' => '1001', 'campaign_name' => 'RON campaign', 'currency_code' => 'RON', 'cost_micros' => 1500000, 'clicks' => 10, 'impressions' => 100, 'conversions' => '2.000000'],
            ['campaign_id' => '1002', 'campaign_name' => 'EUR campaign', 'currency_code' => 'EUR', 'cost_micros' => 2500000, 'clicks' => 20, 'impressions' => 200, 'conversions' => '1.000000'],
            ['campaign_id' => '1001', 'campaign_name' => 'RON campaign', 'currency_code' => 'RON', 'cost_micros' => 500000, 'clicks' => 5, 'impressions' => 50, 'conversions' => '1.000000', 'metric_date' => '2026-10-11'],
        ] as $row) {
            GoogleAdsCampaignDailyMetric::query()->create($row + [
                'account_customer_id' => '1234567890',
                'metric_date' => $row['metric_date'] ?? '2026-10-10',
                'api_version' => 'v26',
                'imported_at' => now(),
            ]);
        }

        $permissions = $this->allPermissions();
        $summary = app(MarketAnalysisDashboardData::class)->summary($permissions, $today);

        $this->assertSame(3, $summary['google_ads']['row_count']);
        $this->assertSame(2, $summary['google_ads']['campaign_count']);
        $this->assertCount(2, $summary['google_ads']['currency_totals']);
        $totals = collect($summary['google_ads']['currency_totals'])->keyBy('currency_code');
        $this->assertSame('2000000', $totals['RON']['cost_micros']);
        $this->assertSame('2500000', $totals['EUR']['cost_micros']);

        $permissions['google_ads_metrics.view'] = false;
        $restricted = app(MarketAnalysisDashboardData::class)->summary($permissions, $today);
        $this->assertFalse($restricted['google_ads']['can_view']);
        $this->assertNull($restricted['google_ads']['row_count']);
        $this->assertSame([], $restricted['google_ads']['currency_totals']);
    }

    public function test_market_trend_observations_are_visible_only_with_market_trend_permission(): void
    {
        MarketTrend::create([
            'name' => 'Search interest', 'category' => 'search_interest', 'metric_key' => 'search_interest_index',
            'value' => 87, 'unit' => 'index', 'market' => 'Romania', 'segment' => 'adult-online',
            'period_start' => '2026-10-01', 'period_end' => '2026-10-25', 'source_name' => 'Public source',
            'source_url' => 'https://example.test/trend', 'observed_at' => '2026-10-26 12:00:00',
            'change_pct' => 4.2, 'confidence' => 0.8, 'status' => 'active',
        ]);
        MarketTrend::create([
            'name' => 'Archived observation', 'category' => 'search_interest', 'metric_key' => 'archived_metric',
            'value' => 12, 'unit' => 'index', 'market' => 'Romania', 'segment' => null,
            'period_start' => '2026-10-01', 'period_end' => '2026-10-25', 'source_name' => 'Old source',
            'source_url' => null, 'observed_at' => '2026-10-26 10:00:00', 'status' => 'archived',
        ]);

        $visible = app(MarketAnalysisDashboardData::class)->summary(['market_trend.index' => true]);
        $this->assertSame(1, $visible['market_trends']['count']);
        $this->assertSame('Search interest', $visible['market_trends']['recent'][0]['name']);
        $this->assertTrue($visible['market_data']['has_audience_demand_data']);

        $hidden = app(MarketAnalysisDashboardData::class)->summary(['market_trend.index' => false]);
        $this->assertNull($hidden['market_trends']['count']);
        $this->assertSame([], $hidden['market_trends']['recent']);
    }

    public function test_dashboard_preserves_missing_and_source_quality_states_without_zero_imputation(): void
    {
        MarketTrend::query()->create([
            'name' => 'Unavailable search index', 'category' => 'search_interest', 'metric_key' => 'search_interest_index',
            'value' => null, 'unit' => 'index', 'market' => 'Romania', 'segment' => null,
            'period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'source_name' => 'Public source',
            'observed_at' => '2026-11-01 10:00:00', 'status' => 'active', 'data_status' => 'missing',
            'missing_reason' => 'The source did not publish a value.', 'source_type' => 'imported',
            'source_reference' => 'provider:missing:2026-10', 'freshness_status' => 'stale',
            'verification_status' => 'unverified',
        ]);

        $summary = app(MarketAnalysisDashboardData::class)->summary(['market_trend.index' => true]);
        $trend = $summary['market_trends']['recent'][0];

        $this->assertNull($trend['value']);
        $this->assertSame('missing', $trend['data_status']);
        $this->assertSame('imported', $trend['source_type']);
        $this->assertSame('stale', $trend['freshness_status']);
        $this->assertSame('unverified', $trend['verification_status']);
        $this->assertSame('provider:missing:2026-10', $trend['source_reference']);
        $this->assertSame('The source did not publish a value.', $trend['missing_reason']);
    }

    public function test_freshness_window_includes_today_and_the_prior_29_days_and_flags_future_dates(): void
    {
        $today = CarbonImmutable::parse('2026-10-18 12:00:00', config('app.timezone'));
        $competitor = Competitor::factory()->create();
        $this->createOffer($competitor, 'Window start', 'active', 'service', $today->subDays(29)->toDateString());
        $this->createOffer($competitor, 'One day stale', 'active', 'service', $today->subDays(30)->toDateString());
        $this->createOffer($competitor, 'Today', 'active', 'service', $today->toDateString());
        $this->createOffer($competitor, 'Future', 'active', 'service', $today->addDay()->toDateString());

        $summary = app(MarketAnalysisDashboardData::class)->summary([
            'competitor_offer.index' => true,
        ], $today);

        $this->assertSame(2, $summary['offers']['checked_recently_count']);
        $this->assertSame(1, $summary['offers']['stale_count']);
        $this->assertSame(1, $summary['offers']['future_dated_count']);
        $this->assertSame('Sep 19, 2026', $summary['freshness_start']);
    }

    public function test_competitor_and_offer_metrics_do_not_leak_without_their_own_list_permissions(): void
    {
        $competitor = Competitor::factory()->create();
        $this->createOffer($competitor, 'Private offer', 'active', 'service', '2026-10-18');

        $now = CarbonImmutable::parse('2026-10-18 12:00:00', config('app.timezone'));
        $competitorsOnly = app(MarketAnalysisDashboardData::class)->summary([
            'competitor.index' => true,
            'competitor_offer.index' => false,
        ], $now);
        $this->assertSame(1, $competitorsOnly['competitors']['current_count']);
        $this->assertNull($competitorsOnly['offers']['current_count']);
        $this->assertSame([], $competitorsOnly['offers']['status_buckets']);
        $this->assertNull($competitorsOnly['offers']['by_competitor']);

        $offersOnly = app(MarketAnalysisDashboardData::class)->summary([
            'competitor.index' => false,
            'competitor_offer.index' => true,
        ], $now);
        $this->assertNull($offersOnly['competitors']['current_count']);
        $this->assertSame(1, $offersOnly['offers']['current_count']);
        $this->assertNull($offersOnly['offers']['by_competitor']);
        $this->assertNull($offersOnly['offers']['recent_checks'][0]['competitor_name']);
        $this->assertNull($offersOnly['offers']['recent_checks'][0]['competitor_site']);
    }

    public function test_current_offers_linked_to_deleted_competitors_are_flagged_as_unavailable_relationships(): void
    {
        $today = CarbonImmutable::parse('2026-10-18 12:00:00', config('app.timezone'));
        $deletedCompetitor = Competitor::factory()->create();
        $deletedCompetitor->delete();
        $this->createOffer($deletedCompetitor, 'Offer with deleted competitor', 'active', 'service', $today->toDateString());

        $summary = app(MarketAnalysisDashboardData::class)->summary([
            'competitor.index' => true,
            'competitor_offer.index' => true,
        ], $today);

        $this->assertSame(1, $summary['offers']['without_current_competitor_count']);
    }

    public function test_empty_inventory_is_zero_but_unavailable_inventory_is_null(): void
    {
        $empty = app(MarketAnalysisDashboardData::class)->summary([
            'competitor.index' => true,
            'competitor_offer.index' => true,
        ]);

        $this->assertSame(0, $empty['competitors']['current_count']);
        $this->assertSame(0, $empty['competitors']['without_locations_count']);
        $this->assertSame(0, $empty['offers']['current_count']);
        $this->assertSame(0, $empty['offers']['stale_count']);
        $this->assertSame([], $empty['offers']['status_buckets']);
        $this->assertSame([], $empty['offers']['recent_checks']);
        $this->assertSame(0, $empty['offers']['observation_count']);
        $this->assertSame([], $empty['offers']['recent_observations']);
        $this->assertFalse($empty['market_data']['has_market_reports']);
        $this->assertFalse($empty['market_data']['has_audience_demand_data']);

        $unavailable = app(MarketAnalysisDashboardData::class)->summary([]);
        $this->assertNull($unavailable['competitors']['current_count']);
        $this->assertNull($unavailable['offers']['current_count']);
        $this->assertSame([], $unavailable['offers']['status_buckets']);
    }

    public function test_quick_links_require_their_own_resource_permissions(): void
    {
        $this->actingAs(User::factory()->create());
        Gate::before(fn ($user, string $ability): ?bool => match ($ability) {
            'competitor.index', 'competitor_offer.create' => true,
            'competitor.create', 'competitor_offer.index' => false,
            default => null,
        });

        $data = (new MarketAnalysis)->getDashboardData();

        $this->assertNotNull($data['competitors']['index_url']);
        $this->assertNull($data['competitors']['create_url']);
        $this->assertNull($data['offers']['index_url']);
        $this->assertNotNull($data['offers']['create_url']);
    }

    public function test_dashboard_keeps_its_dedicated_page_permission(): void
    {
        $page = new MarketAnalysis;
        $this->assertSame(
            ['market-analysis.view' => __('Allows viewing the market analysis page')],
            $page->defineGates(),
        );

        $this->actingAs(User::factory()->create());
        Gate::before(fn ($user, string $ability): ?bool => $ability === 'market-analysis.view' ? true : null);
        $this->assertTrue(MarketAnalysis::canAccess());
    }

    public function test_dashboard_denies_access_without_its_dedicated_page_permission(): void
    {
        $this->actingAs(User::factory()->create());
        Gate::before(fn ($user, string $ability): ?bool => $ability === 'market-analysis.view' ? false : null);

        $this->assertFalse(MarketAnalysis::canAccess());
    }

    /** @return array<string, bool> */
    private function allPermissions(): array
    {
        return [
            'competitor.index' => true,
            'competitor.create' => true,
            'competitor_offer.index' => true,
            'competitor_offer.create' => true,
            'google_ads_metrics.view' => true,
        ];
    }

    private function createOffer(
        Competitor $competitor,
        string $title,
        string $status,
        string $type,
        string $lastCheck,
        string $similarOffer = '',
        string $differentiator = '',
    ): CompetitorOffer {
        return CompetitorOffer::factory()->create([
            'competitor_id' => $competitor->id,
            'title' => $title,
            'status' => $status,
            'offer_type' => $type,
            'offer_price' => 1000,
            'last_check' => $lastCheck,
            'our_similar_offer' => $similarOffer,
            'their_differentiator' => $differentiator,
        ]);
    }

    /** @param array<int, array{label: string, count: int}> $fields */
    private function profileCount(array $summary, string $label): int
    {
        foreach ($summary['competitors']['profile_fields'] as $field) {
            if ($field['label'] === $label) {
                return $field['count'];
            }
        }

        return -1;
    }

    /** @param array<int, array{value: string, count: int}> $buckets @return array<string, int> */
    private function bucketCounts(array $buckets): array
    {
        $counts = [];
        foreach ($buckets as $bucket) {
            $counts[$bucket['value']] = $bucket['count'];
        }
        ksort($counts);

        return $counts;
    }

    /** @param array<int, array{name: string, offer_count: int}> $rows @return array<string, int> */
    private function competitorOfferCounts(array $rows): array
    {
        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['name']] = $row['offer_count'];
        }
        ksort($counts);

        return $counts;
    }
}
