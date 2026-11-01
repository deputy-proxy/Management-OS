<?php

namespace Tests\Feature;

use App\Models\MarketTrend;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class MarketTrendTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_market_observations_with_decimal_date_and_metadata_casts(): void
    {
        $trend = MarketTrend::create($this->validAttributes([
            'value' => 124,
            'previous_value' => 110,
            'change_pct' => 12.7273,
            'confidence' => 0.95,
            'metadata' => ['keyword' => 'cursuri engleza', 'region' => 'Bucuresti'],
        ]));

        $this->assertDatabaseHas('market_trends', [
            'id' => $trend->id,
            'metric_key' => 'search_interest_index',
            'market' => 'Romania',
            'segment' => 'adult-online',
            'value' => '124.000000',
            'confidence' => '0.9500',
        ]);

        $trend->refresh();

        $this->assertSame('124.000000', $trend->value);
        $this->assertSame('110.000000', $trend->previous_value);
        $this->assertSame('12.7273', $trend->change_pct);
        $this->assertSame('0.9500', $trend->confidence);
        $this->assertSame('cursuri engleza', $trend->metadata['keyword']);
        $this->assertInstanceOf(CarbonImmutable::class, $trend->observed_at);
    }

    public function test_dashboard_scopes_filter_by_metric_market_segment_and_overlapping_period(): void
    {
        MarketTrend::create($this->validAttributes([
            'name' => 'Adult online search interest',
            'metric_key' => 'search_interest_index',
            'segment' => 'adult-online',
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
        ]));

        MarketTrend::create($this->validAttributes([
            'name' => 'Children course pricing',
            'category' => 'pricing',
            'metric_key' => 'course_package_price',
            'unit' => 'RON',
            'segment' => 'children',
            'period_start' => '2026-02-01',
            'period_end' => '2026-02-28',
        ]));

        $results = MarketTrend::query()
            ->forMetric('search_interest_index')
            ->forMarket('Romania')
            ->forSegment('adult-online')
            ->withinPeriod('2026-01-15', '2026-02-15')
            ->get();

        $this->assertCount(1, $results);
        $this->assertSame('Adult online search interest', $results->first()->name);
    }

    public function test_observation_identity_is_unique_for_a_non_null_segment(): void
    {
        $attributes = $this->validAttributes();

        MarketTrend::create($attributes);

        $this->expectException(UniqueConstraintViolationException::class);

        MarketTrend::create($attributes);
    }

    public function test_null_segment_identity_cannot_be_inserted_twice(): void
    {
        $attributes = $this->validAttributes(['segment' => null]);
        MarketTrend::query()->create($attributes);

        $this->expectException(InvalidArgumentException::class);
        MarketTrend::query()->create($attributes);
    }

    public function test_numeric_values_respect_decimal_precision_and_range(): void
    {
        foreach ([
            ['value' => -INF],
            ['value' => 100000000000000.0],
            ['previous_value' => 1.1234567],
            ['change_pct' => 1.12345],
            ['confidence' => 0.12345],
        ] as $overrides) {
            try {
                MarketTrend::query()->create($this->validAttributes($overrides));
                $this->fail('Invalid decimal values must be rejected.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_decimal_precision_maxima_are_accepted_without_float_rounding(): void
    {
        $trend = MarketTrend::query()->create($this->validAttributes([
            'value' => '99999999999999.999999',
            'change_pct' => '99999999.9999',
            'confidence' => '1.0000',
        ]));

        $this->assertNotNull($trend->getKey());
    }

    public function test_dashboard_period_filter_rejects_reversed_boundaries(): void
    {
        $this->expectException(InvalidArgumentException::class);
        MarketTrend::query()->withinPeriod('2026-02-01', '2026-01-01')->get();
    }

    public function test_dashboard_period_filter_rejects_invalid_calendar_dates(): void
    {
        $this->expectException(InvalidArgumentException::class);
        MarketTrend::query()->withinPeriod('2026-02-30', '2026-03-01')->get();
    }

    public function test_period_start_cannot_be_after_period_end(): void
    {
        $this->expectException(InvalidArgumentException::class);

        MarketTrend::create($this->validAttributes([
            'period_start' => '2026-02-01',
            'period_end' => '2026-01-01',
        ]));
    }

    public function test_confidence_must_be_between_zero_and_one(): void
    {
        $this->expectException(InvalidArgumentException::class);

        MarketTrend::create($this->validAttributes(['confidence' => 1.01]));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validAttributes(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Adult online search interest',
            'category' => 'search_interest',
            'metric_key' => 'search_interest_index',
            'value' => 100,
            'unit' => 'index',
            'market' => 'Romania',
            'segment' => 'adult-online',
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'source_name' => 'Google Trends',
            'source_url' => 'https://trends.google.com/',
            'observed_at' => '2026-02-01 12:00:00',
            'previous_value' => null,
            'change_pct' => null,
            'confidence' => null,
            'methodology' => 'Normalized relative search interest.',
            'metadata' => null,
            'status' => 'active',
        ], $overrides);
    }
}
