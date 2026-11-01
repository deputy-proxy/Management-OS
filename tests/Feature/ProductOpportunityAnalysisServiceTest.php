<?php

namespace Tests\Feature;

use App\Filament\Pages\ProductOpportunityAnalysis;
use App\Filament\Resources\OpportunityScoringProfiles\OpportunityScoringProfileResource;
use App\Models\Competitor;
use App\Models\CompetitorOffer;
use App\Models\Course;
use App\Models\MarketTrend;
use App\Models\OpportunityScoringProfile;
use App\Models\Product;
use App\Models\User;
use App\Services\OpportunityAnalysis\ProductOpportunityAnalysisService;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Tests\TestCase;

class ProductOpportunityAnalysisServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_weighted_score_is_explainable_and_reproducible_for_a_profile_version(): void
    {
        $now = CarbonImmutable::parse('2026-10-20 12:00:00', config('app.timezone'));
        $this->product('Robotics course');
        $this->trend('demand_index', 80, $now->subDay());
        $this->trend('price_index', 20, $now->subDay());
        $profile = $this->profile([
            $this->factor('demand', 'demand_index', 1),
            $this->factor('price', 'price_index', 3),
        ], ['is_provisional' => false]);

        $service = app(ProductOpportunityAnalysisService::class);
        $first = $service->analyze(['market_trend.index' => true], now: $now);
        $second = $service->analyze(['market_trend.index' => true], now: $now);
        $row = $first['rows'][0];

        $this->assertSame($profile->version, $row['profile_version']);
        $this->assertSame(35.0, $row['score']);
        $this->assertSame('provisional', $row['status']);
        $this->assertSame($first['rows'], $second['rows']);
        $this->assertSame('demand_index', $row['breakdown'][0]['evidence'][0]['label']);
        $this->assertSame(80.0, $row['breakdown'][0]['value']);
        $this->assertSame(20.0, $row['breakdown'][1]['value']);
    }

    public function test_weight_changes_are_explicit_and_change_the_score_without_rewriting_profile_version(): void
    {
        $now = CarbonImmutable::parse('2026-10-20 12:00:00', config('app.timezone'));
        $this->product('Language course');
        $this->trend('demand_index', 80, $now->subDay());
        $this->trend('price_index', 20, $now->subDay());
        $profile = $this->profile([
            $this->factor('demand', 'demand_index', 1),
            $this->factor('price', 'price_index', 3),
        ], ['is_provisional' => false]);
        $service = app(ProductOpportunityAnalysisService::class);
        $before = $service->analyze(['market_trend.index' => true], now: $now)['rows'][0]['score'];

        $this->profile([
            $this->factor('demand', 'demand_index', 1),
            $this->factor('price', 'price_index', 1),
        ], ['version' => 2, 'is_provisional' => false]);
        $after = $service->analyze(['market_trend.index' => true], now: $now)['rows'][0]['score'];

        $this->assertSame(35.0, $before);
        $this->assertSame(50.0, $after);
    }

    public function test_missing_and_stale_signals_remain_unknown_and_are_excluded_from_weighted_average(): void
    {
        $now = CarbonImmutable::parse('2026-10-20 12:00:00', config('app.timezone'));
        $this->product('Design workshop');
        $this->trend('current_signal', 60, $now->subDay());
        $this->trend('stale_signal', 100, $now->subDays(30), ['freshness_status' => 'stale']);
        $this->profile([
            $this->factor('current', 'current_signal', 1),
            $this->factor('stale', 'stale_signal', 10),
            $this->factor('missing', 'missing_signal', 10),
        ], ['is_provisional' => false]);

        $row = app(ProductOpportunityAnalysisService::class)->analyze(['market_trend.index' => true], now: $now)['rows'][0];

        $this->assertSame(60.0, $row['score']);
        $this->assertSame('provisional', $row['status']);
        $this->assertSame(60.0, $row['breakdown'][0]['value']);
        $this->assertNull($row['breakdown'][1]['value']);
        $this->assertNull($row['breakdown'][1]['score']);
        $this->assertNull($row['breakdown'][2]['value']);
        $this->assertStringContainsString('stale', $row['breakdown'][1]['explanation']);
        $this->assertSame('stale', $row['breakdown'][1]['evidence'][0]['freshness']);
        $this->assertSame(60.0, $row['breakdown'][0]['weighted_contribution']);
    }

    public function test_missing_market_observation_preserves_its_reason_and_source_reference(): void
    {
        $now = CarbonImmutable::parse('2026-10-20 12:00:00', config('app.timezone'));
        $this->product('Data science course');
        $this->trend('missing_signal', 0, $now->subDay(), [
            'data_status' => 'missing',
            'value' => null,
            'confidence' => null,
            'missing_reason' => 'Source did not publish this reporting period.',
        ]);
        $this->profile([$this->factor('missing', 'missing_signal', 1)]);

        $row = app(ProductOpportunityAnalysisService::class)->analyze(['market_trend.index' => true], now: $now)['rows'][0];

        $this->assertNull($row['score']);
        $this->assertSame('unknown', $row['status']);
        $this->assertSame('missing', $row['breakdown'][0]['status']);
        $this->assertStringContainsString('Source did not publish', $row['breakdown'][0]['explanation']);
        $this->assertSame('fixture:missing_signal', $row['breakdown'][0]['evidence'][0]['source_reference']);
    }

    public function test_products_with_equal_scores_have_a_stable_title_order(): void
    {
        $now = CarbonImmutable::parse('2026-10-20 12:00:00', config('app.timezone'));
        $this->product('Zulu course');
        $this->product('Alpha course');
        $this->trend('demand_index', 50, $now->subDay());
        $this->profile([$this->factor('demand', 'demand_index', 1)], ['is_provisional' => false]);

        $rows = app(ProductOpportunityAnalysisService::class)->analyze(['market_trend.index' => true], now: $now)['rows'];

        $this->assertSame(['Alpha course', 'Zulu course'], array_column($rows, 'title'));
        $this->assertSame($rows[0]['score'], $rows[1]['score']);
    }

    public function test_filters_apply_to_product_title_type_and_score_state(): void
    {
        $now = CarbonImmutable::parse('2026-10-20 12:00:00', config('app.timezone'));
        $this->product('Alpha course');
        $this->product('Beta course');
        $this->trend('demand_index', 50, $now->subDay());
        $this->profile([$this->factor('demand', 'demand_index', 1)]);

        $result = app(ProductOpportunityAnalysisService::class)->analyze(
            ['market_trend.index' => true],
            ['search' => 'alpha', 'product_type' => 'course', 'status' => 'provisional'],
            $now,
        );

        $this->assertSame(1, $result['count']);
        $this->assertSame('Alpha course', $result['rows'][0]['title']);
        $this->assertSame('provisional', $result['rows'][0]['status']);
    }

    public function test_analysis_returns_an_explicit_empty_state_without_an_active_profile(): void
    {
        $result = app(ProductOpportunityAnalysisService::class)->analyze([]);

        $this->assertNull($result['profile']);
        $this->assertSame([], $result['rows']);
        $this->assertSame(0, $result['count']);
    }

    public function test_source_permissions_hide_evidence_and_return_unknown_instead_of_scoring_it(): void
    {
        $now = CarbonImmutable::parse('2026-10-20 12:00:00', config('app.timezone'));
        $this->product('Science course');
        $this->trend('demand_index', 90, $now->subDay());
        $this->profile([$this->factor('demand', 'demand_index', 1)], ['is_provisional' => false]);

        $row = app(ProductOpportunityAnalysisService::class)->analyze(['market_trend.index' => false], now: $now)['rows'][0];

        $this->assertNull($row['score']);
        $this->assertSame('unknown', $row['status']);
        $this->assertSame([], $row['breakdown'][0]['evidence']);
        $this->assertStringContainsString('not permitted', $row['breakdown'][0]['explanation']);
    }

    public function test_competitor_offer_signal_requires_exact_internal_note_match_and_current_source_snapshot(): void
    {
        $now = CarbonImmutable::parse('2026-10-20 12:00:00', config('app.timezone'));
        $this->product('Robotics course');
        $competitor = Competitor::factory()->create(['name' => 'Synthetic competitor']);
        CompetitorOffer::factory()->create([
            'competitor_id' => $competitor->id,
            'title' => 'Robotics competitor package',
            'our_similar_offer' => 'Robotics course',
            'observed_at' => $now->subDays(2),
            'last_check' => $now->subDays(2)->toDateString(),
            'source_url' => 'https://example.test/competitor/offer',
        ]);
        $this->profile([[
            'key' => 'competitor_presence', 'label' => 'Competitor presence', 'source' => 'competitor_offer_match',
            'weight' => 1, 'direction' => 'higher', 'minimum_value' => 0, 'maximum_value' => 2, 'max_age_days' => 10,
        ]], ['is_provisional' => false]);

        $row = app(ProductOpportunityAnalysisService::class)->analyze(['competitor_offer.index' => true], now: $now)['rows'][0];

        $this->assertSame(50.0, $row['score']);
        $this->assertSame('unverified', $row['breakdown'][0]['status']);
        $this->assertSame('provisional', $row['status']);
        $this->assertSame('https://example.test/competitor/offer', $row['breakdown'][0]['evidence'][0]['source_url']);
    }

    public function test_competitor_offer_snapshot_record_time_does_not_replace_stale_observation_time(): void
    {
        $now = CarbonImmutable::now(config('app.timezone'));
        $this->product('Robotics course');
        $competitor = Competitor::factory()->create();
        CompetitorOffer::factory()->create([
            'competitor_id' => $competitor->id,
            'title' => 'Undated competitor offer',
            'our_similar_offer' => 'Robotics course',
            'observed_at' => null,
            'last_check' => $now->subDays(30)->toDateString(),
            'source_url' => 'https://example.test/undated-offer',
        ]);
        $this->profile([[
            'key' => 'competitor_presence', 'label' => 'Competitor presence', 'source' => 'competitor_offer_match',
            'weight' => 1, 'direction' => 'higher', 'minimum_value' => 0, 'maximum_value' => 2, 'max_age_days' => 10,
        ]]);

        $row = app(ProductOpportunityAnalysisService::class)->analyze(['competitor_offer.index' => true], now: $now)['rows'][0];

        $this->assertNull($row['score']);
        $this->assertSame('unknown', $row['status']);
        $this->assertSame('stale', $row['breakdown'][0]['status']);
        $this->assertNotNull($row['breakdown'][0]['evidence'][0]['observed_at']);
        $this->assertSame('stale', $row['breakdown'][0]['evidence'][0]['freshness']);
    }

    public function test_active_profiles_are_immutable_and_new_versions_retire_the_previous_active_version(): void
    {
        $profile = $this->profile([$this->factor('demand', 'demand_index', 1)]);

        try {
            $profile->update(['factors' => [$this->factor('demand', 'demand_index', 2)]]);
            $this->fail('An active scoring profile must not be rewritten in place.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('immutable', $exception->getMessage());
        }

        $replacement = $this->profile([$this->factor('demand', 'demand_index', 2)], ['version' => 2]);

        $this->assertSame('retired', $profile->fresh()->status);
        $this->assertSame('active', $replacement->fresh()->status);

        try {
            $profile->fresh()->update(['description' => 'Rewrite retired history']);
            $this->fail('A retired scoring profile version must remain immutable.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('immutable', $exception->getMessage());
        }
    }

    public function test_opportunity_page_and_configuration_form_follow_existing_filament_authorization_patterns(): void
    {
        $this->assertInstanceOf(Schema::class, OpportunityScoringProfileResource::form(Schema::make()));
        $this->assertArrayHasKey('market_opportunity.configure', (new OpportunityScoringProfileResource)->defineGates());
        $this->assertArrayHasKey('market_opportunity.view', (new ProductOpportunityAnalysis)->defineGates());

        $this->actingAs(User::factory()->create());
        Gate::before(fn ($user, string $ability): ?bool => $ability === 'market_opportunity.view' ? true : null);
        $this->assertTrue(ProductOpportunityAnalysis::canAccess());
    }

    public function test_opportunity_page_renders_filtered_scores_and_evidence_for_authorized_users(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $now = CarbonImmutable::now(config('app.timezone'));
        $this->product('Visible robotics course');
        $this->trend('demand_index', 75, $now->subDay());
        $this->profile([$this->factor('demand', 'demand_index', 2)]);

        $user = User::factory()->create();
        $roleClass = config('hexa.models.role');
        $role = $roleClass::create([
            'name' => 'Opportunity analysis viewer '.uniqid(),
            'created_by_name' => 'Test',
            'access' => ['Marketing' => ['market_opportunity.view', 'market_opportunity.configure', 'market_trend.index', 'product.index']],
            'guard' => 'web',
        ]);
        $user->roles()->attach($role->getKey());

        $this->actingAs($user)->get(ProductOpportunityAnalysis::getUrl())
            ->assertSuccessful()
            ->assertSee('Product / service')
            ->assertSee('Visible robotics course')
            ->assertSee('75.00 / 100')
            ->assertSee('Provisional')
            ->assertSee('Score explanation and evidence')
            ->assertSee('https://example.test/demand_index')
            ->assertSee('fixture:demand_index')
            ->assertSee('confidence 80.0%');

        $this->get(OpportunityScoringProfileResource::getUrl('index'))
            ->assertSuccessful()
            ->assertSee('Synthetic opportunity rubric');

        $this->get(OpportunityScoringProfileResource::getUrl('create'))
            ->assertSuccessful()
            ->assertSee('Add scoring factor')
            ->assertSee('Market trend observation');
    }

    public function test_opportunity_page_denies_access_without_its_dedicated_permission(): void
    {
        $this->actingAs(User::factory()->create());
        Gate::before(fn ($user, string $ability): ?bool => $ability === 'market_opportunity.view' ? false : null);

        $this->assertFalse(ProductOpportunityAnalysis::canAccess());
    }

    public function test_profile_rejects_duplicate_factor_keys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->profile([
            $this->factor('same', 'demand_index', 1),
            $this->factor('same', 'price_index', 1),
        ]);
    }

    public function test_profile_rejects_invalid_scoring_ranges(): void
    {
        $factor = $this->factor('invalid_range', 'demand_index', 1);
        $factor['minimum_value'] = 100;
        $factor['maximum_value'] = 10;

        $this->expectException(InvalidArgumentException::class);
        $this->profile([$factor]);
    }

    /** @param array<string, mixed> $overrides */
    private function product(string $title, array $overrides = []): Product
    {
        $course = Course::factory()->create(['title' => $title]);
        $product = $course->product()->firstOrFail();
        $product->update(array_merge(['title' => $title, 'status' => 'published'], $overrides));

        return $product->fresh();
    }

    /** @param array<string, mixed> $overrides */
    private function trend(string $metric, float $value, CarbonImmutable $observedAt, array $overrides = []): MarketTrend
    {
        return MarketTrend::query()->create(array_replace([
            'name' => $metric,
            'category' => 'demand',
            'metric_key' => $metric,
            'value' => $value,
            'unit' => 'index',
            'market' => 'RO',
            'segment' => 'adult-online',
            'period_start' => $observedAt->toDateString(),
            'period_end' => $observedAt->toDateString(),
            'source_name' => 'Synthetic fixture',
            'source_url' => 'https://example.test/'.$metric,
            'source_reference' => 'fixture:'.$metric,
            'observed_at' => $observedAt,
            'confidence' => 0.8,
            'methodology' => 'Deterministic test input.',
            'status' => 'active',
            'source_type' => 'synthetic',
            'freshness_status' => 'current',
            'verification_status' => 'unverified',
        ], $overrides));
    }

    /** @return array<string, mixed> */
    private function factor(string $key, string $metric, int $weight): array
    {
        return [
            'key' => $key, 'label' => ucfirst($key), 'source' => 'market_trend', 'metric_key' => $metric,
            'weight' => $weight, 'direction' => 'higher', 'minimum_value' => 0, 'maximum_value' => 100,
            'market' => 'RO', 'segment' => 'adult-online',
            'max_age_days' => 10,
        ];
    }

    /** @param array<int, array<string, mixed>> $factors @param array<string, mixed> $overrides */
    private function profile(array $factors, array $overrides = []): OpportunityScoringProfile
    {
        return OpportunityScoringProfile::query()->create(array_replace([
            'name' => 'Synthetic opportunity rubric', 'version' => 1, 'status' => 'active',
            'is_provisional' => true, 'description' => 'Test-only scoring profile.', 'factors' => $factors,
        ], $overrides));
    }
}
