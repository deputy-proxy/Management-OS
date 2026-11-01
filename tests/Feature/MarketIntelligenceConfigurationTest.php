<?php

namespace Tests\Feature;

use App\Filament\Resources\MarketIntelligenceMarkets\MarketIntelligenceMarketResource;
use App\Filament\Resources\MarketIntelligenceMetrics\MarketIntelligenceMetricResource;
use App\Filament\Resources\MarketIntelligenceSegments\MarketIntelligenceSegmentResource;
use App\Filament\Resources\MarketTrends\Schemas\MarketTrendForm;
use App\Models\MarketIntelligenceMarket;
use App\Models\MarketIntelligenceMetric;
use App\Models\MarketIntelligenceSegment;
use App\Models\MarketTrend;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\MarketIntelligenceDefaultsSeeder;
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Tests\TestCase;

class MarketIntelligenceConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_provisional_defaults_are_idempotent_and_do_not_overwrite_owner_edits(): void
    {
        $seeder = app(MarketIntelligenceDefaultsSeeder::class);
        $seeder->run();

        $market = MarketIntelligenceMarket::query()->where('code', 'RO')->firstOrFail();
        $metric = MarketIntelligenceMetric::query()->where('key', 'search_interest_index')->firstOrFail();

        $this->assertTrue($market->is_provisional);
        $this->assertSame('country', $market->geography_type);
        $this->assertTrue($metric->is_provisional);
        $this->assertSame(['index'], $metric->allowed_units);

        $market->update(['name' => 'Owner-defined Romania']);
        $seeder->run();

        $this->assertSame('Owner-defined Romania', $market->fresh()->name);
        $this->assertSame(2, MarketIntelligenceSegment::query()->count());
        $this->assertSame(2, MarketIntelligenceMetric::query()->count());
    }

    public function test_metric_definition_rejects_invalid_keys_types_units_and_period_limits(): void
    {
        $this->expectException(InvalidArgumentException::class);

        MarketIntelligenceMetric::query()->create([
            'key' => 'Invalid Key', 'name' => 'Invalid', 'category' => 'demand',
            'value_type' => 'unsupported', 'allowed_units' => [], 'period_granularity' => 'decade',
        ]);
    }

    public function test_metric_definition_rejects_unknown_value_types_and_duplicate_or_empty_units(): void
    {
        foreach ([
            ['value_type' => 'unclassified', 'allowed_units' => ['index']],
            ['value_type' => 'index', 'allowed_units' => []],
            ['value_type' => 'index', 'allowed_units' => ['index', 'index']],
            ['value_type' => 'index', 'allowed_units' => ['']],
        ] as $index => $overrides) {
            try {
                MarketIntelligenceMetric::query()->create(array_replace([
                    'key' => 'invalid_metric_'.$index, 'name' => 'Invalid metric', 'category' => 'demand',
                    'value_type' => 'index', 'allowed_units' => ['index'], 'period_granularity' => 'month',
                ], $overrides));
                $this->fail('Invalid metric types and units must be rejected.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_filament_forms_for_market_configuration_and_trends_can_be_constructed(): void
    {
        foreach ([
            MarketIntelligenceMarketResource::class,
            MarketIntelligenceSegmentResource::class,
            MarketIntelligenceMetricResource::class,
        ] as $resourceClass) {
            $this->assertInstanceOf(Schema::class, $resourceClass::form(Schema::make()));
        }

        $this->assertInstanceOf(Schema::class, MarketTrendForm::configure(Schema::make()));
    }

    public function test_metric_definition_accepts_a_valid_configurable_contract(): void
    {
        $metric = MarketIntelligenceMetric::query()->create([
            'key' => 'monthly_enrolment_count', 'name' => 'Monthly enrolment count', 'category' => 'demand',
            'value_type' => 'count', 'allowed_units' => ['students'], 'period_granularity' => 'month',
            'max_period_days' => 31, 'is_active' => true, 'is_provisional' => true,
        ]);

        $this->assertTrue($metric->acceptsUnit('students'));
        $this->assertFalse($metric->acceptsUnit('RON'));
        $this->assertSame(['students'], $metric->fresh()->allowed_units);
    }

    public function test_trend_explicitly_links_matching_definitions_and_rejects_incompatible_units(): void
    {
        $this->seed(MarketIntelligenceDefaultsSeeder::class);
        $metric = MarketIntelligenceMetric::query()->where('key', 'search_interest_index')->firstOrFail();
        $market = MarketIntelligenceMarket::query()->where('code', 'RO')->firstOrFail();
        $segment = MarketIntelligenceSegment::query()->where('code', 'adult-online')->firstOrFail();

        $trend = MarketTrend::query()->create($this->trendAttributes([
            'metric_key' => $metric->key,
            'market_intelligence_metric_id' => $metric->id,
            'market' => $market->code,
            'market_intelligence_market_id' => $market->id,
            'segment' => $segment->code,
            'market_intelligence_segment_id' => $segment->id,
            'unit' => 'index',
        ]));

        $this->assertSame($metric->id, $trend->market_intelligence_metric_id);
        $this->assertSame($market->id, $trend->market_intelligence_market_id);
        $this->assertSame($segment->id, $trend->market_intelligence_segment_id);

        try {
            MarketTrend::query()->create($this->trendAttributes([
                'metric_key' => $metric->key,
                'market_intelligence_metric_id' => $metric->id,
                'unit' => 'RON',
            ]));
            $this->fail('An incompatible unit must be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('unit is not allowed', $exception->getMessage());
        }
    }

    public function test_removing_a_configuration_definition_preserves_legacy_observation_values(): void
    {
        $this->seed(MarketIntelligenceDefaultsSeeder::class);
        $metric = MarketIntelligenceMetric::query()->where('key', 'search_interest_index')->firstOrFail();
        $market = MarketIntelligenceMarket::query()->where('code', 'RO')->firstOrFail();
        $segment = MarketIntelligenceSegment::query()->where('code', 'adult-online')->firstOrFail();
        $trend = MarketTrend::query()->create($this->trendAttributes([
            'metric_key' => $metric->key, 'market_intelligence_metric_id' => $metric->id,
            'market' => $market->code, 'market_intelligence_market_id' => $market->id,
            'segment' => $segment->code, 'market_intelligence_segment_id' => $segment->id,
        ]));

        $metric->delete();
        $market->delete();
        $segment->delete();
        $trend->refresh();

        $this->assertNull($trend->market_intelligence_metric_id);
        $this->assertNull($trend->market_intelligence_market_id);
        $this->assertNull($trend->market_intelligence_segment_id);
        $this->assertSame('search_interest_index', $trend->metric_key);
        $this->assertSame('RO', $trend->market);
        $this->assertSame('adult-online', $trend->segment);
    }

    public function test_trend_rejects_periods_that_exceed_the_configured_metric_limit(): void
    {
        $this->seed(MarketIntelligenceDefaultsSeeder::class);

        try {
            $metric = MarketIntelligenceMetric::query()->where('key', 'search_interest_index')->firstOrFail();
            MarketTrend::query()->create($this->trendAttributes([
                'market_intelligence_metric_id' => $metric->id,
                'period_start' => '2026-01-01',
                'period_end' => '2027-01-02',
            ]));
            $this->fail('The metric maximum period must be enforced.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('exceeds the metric definition', $exception->getMessage());
        }
    }

    public function test_missing_observations_require_a_reason_and_do_not_fabricate_a_numeric_value(): void
    {
        $trend = MarketTrend::query()->create($this->trendAttributes([
            'data_status' => 'missing',
            'value' => null,
            'confidence' => null,
            'missing_reason' => 'Source did not publish the selected period.',
        ]));

        $this->assertNull($trend->value);
        $this->assertSame('missing', $trend->data_status);
        $this->assertSame('Source did not publish the selected period.', $trend->missing_reason);

        try {
            MarketTrend::query()->create($this->trendAttributes([
                'data_status' => 'missing', 'value' => 0, 'missing_reason' => 'No source value.',
            ]));
            $this->fail('Missing data must not be represented as numeric zero.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('no numeric value', $exception->getMessage());
        }
    }

    public function test_source_type_freshness_and_verification_are_independent_states(): void
    {
        $trend = MarketTrend::query()->create($this->trendAttributes([
            'source_type' => 'imported',
            'source_reference' => 'provider:dataset:record:123',
            'source_revision' => 'revision-7',
            'source_metadata' => ['dataset' => 'synthetic-dataset', 'region' => 'RO'],
            'freshness_status' => 'stale',
            'verification_status' => 'verified',
            'source_url' => 'https://source.example.test/record/123',
        ]));

        $this->assertSame('imported', $trend->source_type);
        $this->assertSame('stale', $trend->freshness_status);
        $this->assertSame('verified', $trend->verification_status);
        $this->assertSame('revision-7', $trend->source_revision);
        $this->assertSame('synthetic-dataset', $trend->source_metadata['dataset']);

        foreach ([
            ['source_type' => 'synthetic', 'verification_status' => 'verified', 'source_reference' => 'fixture:1'],
            ['source_type' => 'imported', 'source_reference' => null],
            ['verification_status' => 'verified', 'source_reference' => null, 'source_url' => null],
        ] as $overrides) {
            try {
                MarketTrend::query()->create($this->trendAttributes($overrides));
                $this->fail('Invalid provenance combinations must be rejected.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_confidence_bounds_include_zero_and_one_but_reject_values_outside_the_range(): void
    {
        $zero = MarketTrend::query()->create($this->trendAttributes(['confidence' => 0]));
        $one = MarketTrend::query()->create($this->trendAttributes(['confidence' => 1, 'name' => 'Upper bound', 'period_start' => '2026-02-01', 'period_end' => '2026-02-28']));

        $this->assertSame('0.0000', $zero->confidence);
        $this->assertSame('1.0000', $one->confidence);

        $this->expectException(InvalidArgumentException::class);
        MarketTrend::query()->create($this->trendAttributes(['confidence' => -0.01]));
    }

    public function test_market_definition_ids_must_match_the_legacy_observation_codes(): void
    {
        $this->seed(MarketIntelligenceDefaultsSeeder::class);
        $market = MarketIntelligenceMarket::query()->where('code', 'RO')->firstOrFail();

        try {
            MarketTrend::query()->create($this->trendAttributes([
                'market_intelligence_market_id' => $market->id,
                'market' => 'Bucharest',
            ]));
            $this->fail('A market definition cannot be linked to a different market code.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('market definition must match', $exception->getMessage());
        }
    }

    public function test_configuration_resource_pages_render_only_for_users_with_view_permission(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $user = User::factory()->create();
        $roleClass = config('hexa.models.role');
        $role = $roleClass::create([
            'name' => 'Market intelligence viewer '.uniqid(),
            'created_by_name' => 'Test',
            'access' => ['Marketing' => ['market_intelligence_configuration.view']],
            'guard' => 'web',
        ]);
        $user->roles()->attach($role->getKey());

        $this->actingAs($user)->get(MarketIntelligenceMetricResource::getUrl('index'))
            ->assertSuccessful()
            ->assertSee('Metric Definitions');
        $this->get(MarketIntelligenceMetricResource::getUrl('create'))->assertForbidden();
    }

    public function test_configuration_resources_expose_separate_view_and_manage_permissions(): void
    {
        $resource = new MarketIntelligenceMetricResource;

        $expectedGates = [
            'market_intelligence_configuration.view' => __('Allows viewing market intelligence configuration'),
            'market_intelligence_configuration.manage' => __('Allows managing market intelligence configuration'),
        ];
        $this->assertSame($expectedGates, $resource->defineGates());
        $this->assertSame($expectedGates, (new MarketIntelligenceMarketResource)->defineGates());
        $this->assertSame($expectedGates, (new MarketIntelligenceSegmentResource)->defineGates());

        $this->actingAs(User::factory()->create());
        Gate::before(fn ($user, string $ability): ?bool => match ($ability) {
            'market_intelligence_configuration.view' => true,
            'market_intelligence_configuration.manage' => false,
            default => null,
        });

        foreach ([MarketIntelligenceMetricResource::class, MarketIntelligenceMarketResource::class, MarketIntelligenceSegmentResource::class] as $resourceClass) {
            $this->assertTrue($resourceClass::getViewAnyAuthorizationResponse()->allowed());
            $this->assertFalse($resourceClass::getCreateAuthorizationResponse()->allowed());
        }
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function trendAttributes(array $overrides = []): array
    {
        static $sequence = 0;
        $sequence++;

        return array_replace([
            'name' => 'Synthetic market observation '.$sequence,
            'category' => 'search_interest',
            'metric_key' => 'search_interest_index',
            'value' => '75.000000',
            'unit' => 'index',
            'market' => 'Romania',
            'segment' => 'adult-online',
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'source_name' => 'Synthetic fixture',
            'source_url' => null,
            'observed_at' => CarbonImmutable::parse('2026-02-01 12:00:00'),
            'previous_value' => null,
            'change_pct' => null,
            'confidence' => null,
            'methodology' => 'Deterministic synthetic fixture.',
            'metadata' => null,
            'status' => 'active',
            'data_status' => 'present',
            'source_type' => 'synthetic',
            'freshness_status' => 'unknown',
            'verification_status' => 'unverified',
            'source_reference' => null,
            'source_revision' => null,
            'source_metadata' => null,
            'missing_reason' => null,
        ], $overrides);
    }
}
