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

    protected function casts(): array
    {
        return [
            'offer_price' => 'decimal:2',
            'last_check' => 'date',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'observed_at' => 'immutable_datetime',
        ];
    }

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
            $isNewOrDirty = static fn (string $field): bool => ! $offer->exists || $offer->isDirty($field);

            foreach (['title', 'offer_type', 'offer_description', 'status'] as $field) {
                if ($isNewOrDirty($field) && trim((string) $offer->{$field}) === '') {
                    throw new InvalidArgumentException("Competitor offer {$field} is required.");
                }
            }

            if ($isNewOrDirty('competitor_id') && (! $offer->competitor_id || ! Competitor::withTrashed()->whereKey($offer->competitor_id)->exists())) {
                throw new InvalidArgumentException('An existing competitor record must be selected for an offer.');
            }

            $rawPrice = $offer->getAttributes()['offer_price'] ?? null;
            $wholePrice = ltrim(explode('.', (string) $rawPrice)[0], '0');
            if ($isNewOrDirty('offer_price') && (! is_numeric($rawPrice) || ! is_finite((float) $rawPrice) || (float) $rawPrice < 0 || strlen($wholePrice) > 12 || preg_match('/\A\d+(?:\.\d{1,2})?\z/', (string) $rawPrice) !== 1)) {
                throw new InvalidArgumentException('Competitor offer price must be between 0 and 999999999999.99 with at most two decimal places.');
            }

            if ($isNewOrDirty('currency') && filled($offer->currency)) {
                $offer->currency = strtoupper(trim((string) $offer->currency));
                if (preg_match('/\A[A-Z]{3}\z/', $offer->currency) !== 1) {
                    throw new InvalidArgumentException('Offer currency must be a three-letter ISO currency code or unknown.');
                }
            }

            if ($isNewOrDirty('price_basis') && filled($offer->price_basis) && ! in_array($offer->price_basis, ['one_time', 'monthly', 'annual', 'per_unit', 'from', 'unknown'], true)) {
                throw new InvalidArgumentException('Choose a supported competitor offer price basis.');
            }

            if ($isNewOrDirty('source_url') && filled($offer->source_url) && (mb_strlen($offer->source_url) > 2048 || filter_var($offer->source_url, FILTER_VALIDATE_URL) === false)) {
                throw new InvalidArgumentException('Offer source URL must be a valid URL of at most 2048 characters.');
            }

            try {
                $validFrom = $isNewOrDirty('valid_from') || $isNewOrDirty('valid_until') ? self::parseDateAttribute($offer, 'valid_from') : null;
                $validUntil = $isNewOrDirty('valid_from') || $isNewOrDirty('valid_until') ? self::parseDateAttribute($offer, 'valid_until') : null;
                if ($isNewOrDirty('last_check')) {
                    self::parseDateAttribute($offer, 'last_check');
                }
                if ($validFrom && $validUntil && $validFrom->gt($validUntil)) {
                    throw new InvalidArgumentException('Offer validity start must be on or before its end.');
                }
                if ($isNewOrDirty('observed_at') && $offer->observed_at && Carbon::parse($offer->observed_at)->gt(now())) {
                    throw new InvalidArgumentException('Offer observation timestamp cannot be in the future.');
                }
            } catch (\Throwable $exception) {
                if ($exception instanceof InvalidArgumentException) {
                    throw $exception;
                }
                throw new InvalidArgumentException('Offer validity dates must be valid dates.', previous: $exception);
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

    private static function parseDateAttribute(self $offer, string $field): ?Carbon
    {
        $raw = $offer->getAttributes()[$field] ?? null;
        if ($raw === null || $raw === '') {
            return null;
        }

        $date = is_string($raw) ? substr($raw, 0, 10) : '';
        if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $date) !== 1) {
            throw new InvalidArgumentException("Offer {$field} must use YYYY-MM-DD format.");
        }

        try {
            $parsed = Carbon::createFromFormat('!Y-m-d', $date);
        } catch (\Throwable $exception) {
            throw new InvalidArgumentException("Offer {$field} must be a valid calendar date.", previous: $exception);
        }
        if (! $parsed || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException("Offer {$field} must be a valid calendar date.");
        }

        return $parsed;
    }

    private function recordObservation(): void
    {
        $snapshot = [
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
        ];
        $snapshot['observation_key'] = CompetitorOfferObservation::makeObservationKey([
            'competitor_offer_id' => $this->getKey(),
            ...$snapshot,
        ]);

        $this->observations()->firstOrCreate(
            ['observation_key' => $snapshot['observation_key']],
            $snapshot,
        );
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['title', 'competitor_id', 'offer_type', 'offer_price', 'last_check', 'status', 'source_url', 'currency', 'price_basis', 'valid_from', 'valid_until', 'observed_at'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
