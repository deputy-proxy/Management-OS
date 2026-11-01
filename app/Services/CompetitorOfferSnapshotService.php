<?php

namespace App\Services;

use App\Models\CompetitorOffer;
use App\Models\CompetitorOfferObservation;

class CompetitorOfferSnapshotService
{
    public function record(CompetitorOffer $offer): CompetitorOfferObservation
    {
        $snapshot = [
            'title' => $offer->title,
            'offer_type' => $offer->offer_type,
            'offer_description' => $offer->offer_description,
            'offer_price' => $offer->offer_price,
            'currency' => $offer->currency,
            'price_basis' => $offer->price_basis,
            'source_url' => $offer->source_url,
            'valid_from' => $offer->valid_from,
            'valid_until' => $offer->valid_until,
            'observed_at' => $offer->observed_at ?? $offer->last_check ?? now(),
            'recorded_at' => now(),
            'recorded_by' => auth()->id(),
            'source_status' => filled($offer->source_url) ? 'provided' : 'unknown',
        ];
        $snapshot['observation_key'] = CompetitorOfferObservation::makeObservationKey([
            'competitor_offer_id' => $offer->getKey(),
            ...$snapshot,
        ]);

        return $offer->observations()->firstOrCreate(
            ['observation_key' => $snapshot['observation_key']],
            $snapshot,
        );
    }
}
