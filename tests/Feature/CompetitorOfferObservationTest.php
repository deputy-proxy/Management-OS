<?php

namespace Tests\Feature;

use App\Models\CompetitorOffer;
use App\Models\CompetitorOfferObservation;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompetitorOfferObservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_an_offer_records_a_provenance_snapshot(): void
    {
        $offer = CompetitorOffer::factory()->create([
            'title' => 'Basic plan',
            'offer_price' => 99.50,
            'currency' => 'RON',
            'price_basis' => 'monthly',
            'source_url' => 'https://example.test/pricing',
            'observed_at' => '2026-10-01 10:00:00',
        ]);

        $this->assertSame(1, $offer->observations()->count());
        $snapshot = $offer->observations()->firstOrFail();
        $this->assertSame('Basic plan', $snapshot->title);
        $this->assertSame('99.50', $snapshot->offer_price);
        $this->assertSame('99.50', $offer->fresh()->offer_price);
        $this->assertSame('RON', $snapshot->currency);
        $this->assertSame('monthly', $snapshot->price_basis);
        $this->assertSame('https://example.test/pricing', $snapshot->source_url);
        $this->assertSame('provided', $snapshot->source_status);
    }

    public function test_editing_an_offer_appends_a_snapshot_without_overwriting_history(): void
    {
        $offer = CompetitorOffer::factory()->create(['offer_price' => 100, 'currency' => 'RON']);
        $first = $offer->observations()->firstOrFail();

        $offer->update(['offer_price' => 125, 'observed_at' => now()->subDay()]);

        $this->assertSame(2, CompetitorOfferObservation::query()->where('competitor_offer_id', $offer->id)->count());
        $this->assertSame('100.00', $first->fresh()->offer_price);
        $this->assertSame('125.00', CompetitorOfferObservation::query()->where('competitor_offer_id', $offer->id)->orderByDesc('id')->firstOrFail()->offer_price);
    }

    public function test_offer_currency_is_normalized_and_invalid_validity_windows_are_rejected(): void
    {
        $offer = CompetitorOffer::factory()->create(['currency' => 'ron']);
        $this->assertSame('RON', $offer->fresh()->currency);

        $this->expectException(\InvalidArgumentException::class);
        $offer->update(['valid_from' => '2026-11-10', 'valid_until' => '2026-11-01']);
    }

    public function test_repeated_snapshot_event_is_idempotent_and_history_is_immutable(): void
    {
        $offer = CompetitorOffer::factory()->create([
            'offer_price' => 100,
            'observed_at' => '2026-10-01 10:00:00',
        ]);
        $snapshot = $offer->observations()->firstOrFail();

        $duplicate = $snapshot->getAttributes();
        unset($duplicate['id']);
        $duplicateModel = new CompetitorOfferObservation($duplicate);
        $duplicateModel->competitor_offer_id = $offer->id;

        $this->expectException(UniqueConstraintViolationException::class);
        $duplicateModel->save();
    }

    public function test_historical_observations_cannot_be_updated_or_deleted_and_survive_offer_soft_deletion(): void
    {
        $offer = CompetitorOffer::factory()->create();
        $snapshot = $offer->observations()->firstOrFail();

        try {
            $snapshot->update(['offer_price' => 1]);
            $this->fail('Historical snapshots must be immutable.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('immutable', $exception->getMessage());
        }

        $offer->delete();
        $this->assertSoftDeleted('competitor_offers', ['id' => $offer->id]);
        $this->assertSame(1, CompetitorOfferObservation::query()->where('competitor_offer_id', $offer->id)->count());

        $this->expectException(QueryException::class);
        $offer->forceDelete();
    }

    public function test_offer_rejects_negative_prices_invalid_price_basis_and_invalid_source_urls(): void
    {
        $offer = CompetitorOffer::factory()->create();
        foreach ([
            ['offer_price' => -1],
            ['offer_price' => 1.234],
            ['valid_from' => '2026-02-30'],
            ['price_basis' => 'per_week'],
            ['source_url' => 'not-a-url'],
        ] as $attributes) {
            try {
                $offer->update($attributes);
                $this->fail('Invalid offer attributes must be rejected.');
            } catch (\InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_observation_model_rejects_invalid_date_order_and_source_state(): void
    {
        $offer = CompetitorOffer::factory()->create();
        $snapshot = $offer->observations()->firstOrFail();

        foreach ([
            ['valid_from' => '2026-11-10', 'valid_until' => '2026-11-01'],
            ['observed_at' => now()->addDay(), 'recorded_at' => now()],
            ['source_status' => 'guessed'],
            ['currency' => 'EURO'],
        ] as $overrides) {
            $attributes = array_replace($snapshot->getAttributes(), $overrides);
            $observation = new CompetitorOfferObservation($attributes);
            $observation->competitor_offer_id = $offer->id;
            try {
                $observation->save();
                $this->fail('Invalid historical snapshot attributes must be rejected.');
            } catch (\InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_schema_rollback_refuses_to_truncate_fractional_offer_prices(): void
    {
        CompetitorOffer::factory()->create(['offer_price' => '99.50']);
        $migration = require database_path('migrations/2026_11_01_140000_harden_market_data_model_constraints.php');

        $this->expectException(\RuntimeException::class);
        $migration->down();
    }

    public function test_offer_history_compares_only_matching_currency_and_price_basis(): void
    {
        $offer = CompetitorOffer::factory()->create([
            'offer_price' => '100.00', 'currency' => 'RON', 'price_basis' => 'monthly',
            'observed_at' => '2026-10-01 10:00:00',
        ]);
        $baseline = $offer->observations()->firstOrFail();
        $this->assertSame('Baseline observation', $baseline->priceComparisonLabel());

        $offer->update(['offer_price' => '125.00', 'observed_at' => '2026-10-02 10:00:00']);
        $increase = $offer->observations()->firstOrFail();
        $this->assertSame('+25.00 RON (same basis)', $increase->priceComparisonLabel());

        $offer->update(['currency' => 'EUR', 'observed_at' => '2026-10-03 10:00:00']);
        $currencyChange = $offer->observations()->firstOrFail();
        $this->assertSame('Not comparable: currency changed', $currencyChange->priceComparisonLabel());

        $offer->update(['price_basis' => 'annual', 'observed_at' => '2026-10-04 10:00:00']);
        $basisChange = $offer->observations()->firstOrFail();
        $this->assertSame('Not comparable: price basis changed', $basisChange->priceComparisonLabel());

        $unknown = CompetitorOffer::factory()->create([
            'offer_price' => '50.00', 'currency' => null, 'price_basis' => null,
            'observed_at' => '2026-10-05 10:00:00',
        ]);
        $unknownFirst = $unknown->observations()->firstOrFail();
        $unknown->update(['offer_price' => '55.00', 'observed_at' => '2026-10-06 10:00:00']);
        $this->assertSame('Not comparable: currency is unknown', $unknown->observations()->firstOrFail()->priceComparisonLabel());
        $this->assertSame('Baseline observation', $unknownFirst->fresh()->priceComparisonLabel());
    }

    public function test_missing_source_is_explicitly_unknown_not_inferred(): void
    {
        $offer = CompetitorOffer::factory()->create(['source_url' => null, 'currency' => null]);

        $snapshot = $offer->observations()->firstOrFail();
        $this->assertSame('unknown', $snapshot->source_status);
        $this->assertNull($snapshot->currency);
        $this->assertNull($snapshot->source_url);
    }
}
