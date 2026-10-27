<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitorOfferObservation extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'title', 'offer_type', 'offer_description', 'offer_price', 'currency', 'price_basis',
        'source_url', 'valid_from', 'valid_until', 'observed_at', 'recorded_at', 'recorded_by', 'source_status',
    ];

    protected function casts(): array
    {
        return [
            'offer_price' => 'decimal:2',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'observed_at' => 'immutable_datetime',
            'recorded_at' => 'immutable_datetime',
        ];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(CompetitorOffer::class, 'competitor_offer_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
