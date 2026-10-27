<?php

namespace Tests\Feature;

use App\Models\CompetitorOffer;
use App\Models\CompetitorOfferObservation;
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

    public function test_missing_source_is_explicitly_unknown_not_inferred(): void
    {
        $offer = CompetitorOffer::factory()->create(['source_url' => null, 'currency' => null]);

        $snapshot = $offer->observations()->firstOrFail();
        $this->assertSame('unknown', $snapshot->source_status);
        $this->assertNull($snapshot->currency);
        $this->assertNull($snapshot->source_url);
    }
}
