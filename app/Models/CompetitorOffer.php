<?php

namespace App\Models;

use App\Models\Concerns\HasPanelSearchTitle;
use App\Models\Concerns\HasTasks;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Relaticle\Comments\Concerns\HasComments;
use Relaticle\Comments\Contracts\Commentable;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class CompetitorOffer extends Model implements Commentable
{
    use HasComments;
    use HasFactory;
    use HasPanelSearchTitle;
    use HasTasks;
    use LogsActivity;
    use SoftDeletes;

    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    /** @var array<int, string> */
    protected $fillable = [
        'title', 'competitor_id', 'offer_type', 'offer_description', 'offer_price', 'our_similar_offer', 'their_differentiator', 'last_check', 'status', 'source_url', 'currency', 'price_basis', 'valid_from', 'valid_until', 'observed_at',
    ];

    public function competitor(): BelongsTo
    {
        return $this->belongsTo(Competitor::class);
    }

    /** @return HasMany<CompetitorOfferObservation, $this> */
    public function observations(): HasMany
    {
        return $this->hasMany(CompetitorOfferObservation::class)->orderByDesc('observed_at')->orderByDesc('id');
    }

    protected static function booted(): void
    {
        static::saving(function (self $offer): void {
            if (filled($offer->currency)) {
                $offer->currency = strtoupper(trim((string) $offer->currency));
                if (preg_match('/\A[A-Z]{3}\z/', $offer->currency) !== 1) {
                    throw new InvalidArgumentException('Offer currency must be a three-letter ISO currency code or unknown.');
                }
            }

            if ($offer->valid_from && $offer->valid_until && Carbon::parse($offer->valid_from)->gt(Carbon::parse($offer->valid_until))) {
                throw new InvalidArgumentException('Offer validity start must be on or before its end.');
            }
        });

        static::created(fn (self $offer) => $offer->recordObservation());
        static::updated(function (self $offer): void {
            if ($offer->wasChanged([
                'title', 'competitor_id', 'offer_type', 'offer_description', 'offer_price',
                'our_similar_offer', 'their_differentiator', 'last_check', 'status',
                'source_url', 'currency', 'price_basis', 'valid_from', 'valid_until', 'observed_at',
            ])) {
                $offer->recordObservation();
            }
        });
    }

    private function recordObservation(): void
    {
        $this->observations()->create([
            'title' => $this->title,
            'offer_type' => $this->offer_type,
            'offer_description' => $this->offer_description,
            'offer_price' => $this->offer_price,
            'currency' => $this->currency,
            'price_basis' => $this->price_basis,
            'source_url' => $this->source_url,
            'valid_from' => $this->valid_from,
            'valid_until' => $this->valid_until,
            'observed_at' => $this->observed_at ?? $this->last_check ?? now(),
            'recorded_at' => now(),
            'recorded_by' => auth()->id(),
            'source_status' => filled($this->source_url) ? 'provided' : 'unknown',
        ]);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['title', 'competitor_id', 'offer_type', 'offer_price', 'last_check', 'status', 'source_url', 'currency', 'price_basis', 'valid_from', 'valid_until', 'observed_at'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
