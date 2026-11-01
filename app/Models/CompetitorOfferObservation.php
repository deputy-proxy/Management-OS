<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use LogicException;

class CompetitorOfferObservation extends Model
{
    public $timestamps = false;

    /** @var array<string, mixed> */
    protected $attributes = ['source_status' => 'unknown'];

    protected $fillable = [
        'observation_key', 'title', 'offer_type', 'offer_description', 'offer_price', 'currency', 'price_basis',
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

    protected static function booted(): void
    {
        static::creating(function (self $observation): void {
            $observation->validateSnapshot();
            $observation->observation_key ??= self::makeObservationKey($observation->getAttributes());
        });

        static::updating(fn () => throw new LogicException('Competitor offer observations are immutable historical records.'));
        static::deleting(fn () => throw new LogicException('Competitor offer observations are retained as historical records.'));
    }

    /** @param array<string, mixed> $attributes */
    public static function makeObservationKey(array $attributes): string
    {
        $fields = [
            'competitor_offer_id', 'title', 'offer_type', 'offer_description', 'offer_price', 'currency',
            'price_basis', 'source_url', 'valid_from', 'valid_until', 'observed_at', 'recorded_by', 'source_status',
        ];
        $identity = [];
        foreach ($fields as $field) {
            $value = $attributes[$field] ?? null;
            if ($value instanceof Carbon) {
                $value = $value->toISOString();
            }
            $identity[$field] = $value;
        }

        return hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    /** @return BelongsTo<CompetitorOffer, $this> */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(CompetitorOffer::class, 'competitor_offer_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    private function validateSnapshot(): void
    {
        foreach (['title', 'offer_type', 'offer_description'] as $field) {
            if (trim((string) $this->{$field}) === '') {
                throw new InvalidArgumentException("Competitor offer observation {$field} is required.");
            }
        }
        $rawPrice = $this->getAttributes()['offer_price'] ?? null;
        if ($rawPrice !== null) {
            self::validatePrice($rawPrice);
        }
        if (filled($this->currency)) {
            $this->currency = strtoupper(trim((string) $this->currency));
            if (preg_match('/\A[A-Z]{3}\z/', $this->currency) !== 1) {
                throw new InvalidArgumentException('Observation currency must be a three-letter ISO currency code.');
            }
        }
        if (! in_array($this->source_status, ['provided', 'unknown'], true)) {
            throw new InvalidArgumentException('Observation source status must be provided or unknown.');
        }
        if (filled($this->source_url) && (mb_strlen($this->source_url) > 2048 || filter_var($this->source_url, FILTER_VALIDATE_URL) === false)) {
            throw new InvalidArgumentException('Observation source URL must be a valid URL of at most 2048 characters.');
        }
        $validFrom = self::parseDateAttribute($this, 'valid_from');
        $validUntil = self::parseDateAttribute($this, 'valid_until');
        if ($validFrom && $validUntil && $validFrom->gt($validUntil)) {
            throw new InvalidArgumentException('Observation validity start must be on or before its end.');
        }
        if (! $this->observed_at || ! $this->recorded_at) {
            throw new InvalidArgumentException('Observation and record timestamps are required.');
        }
        if (Carbon::parse($this->observed_at)->gt(Carbon::parse($this->recorded_at))) {
            throw new InvalidArgumentException('An observation cannot be recorded before its observation timestamp.');
        }
    }

    private static function parseDateAttribute(self $observation, string $field): ?Carbon
    {
        $raw = $observation->getAttributes()[$field] ?? null;
        if ($raw === null || $raw === '') {
            return null;
        }

        $date = is_string($raw) ? substr($raw, 0, 10) : '';
        if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $date) !== 1) {
            throw new InvalidArgumentException("Observation {$field} must use YYYY-MM-DD format.");
        }

        try {
            $parsed = Carbon::createFromFormat('!Y-m-d', $date);
        } catch (\Throwable $exception) {
            throw new InvalidArgumentException("Observation {$field} must be a valid calendar date.", previous: $exception);
        }
        if (! $parsed || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException("Observation {$field} must be a valid calendar date.");
        }

        return $parsed;
    }

    private static function validatePrice(mixed $price): void
    {
        $wholePrice = ltrim(explode('.', (string) $price)[0], '0');
        if (! is_numeric($price) || ! is_finite((float) $price) || (float) $price < 0 || strlen($wholePrice) > 12) {
            throw new InvalidArgumentException('Competitor offer observation price must be between 0 and 999999999999.99.');
        }
        if (preg_match('/\A\d+(?:\.\d{1,2})?\z/', (string) $price) !== 1) {
            throw new InvalidArgumentException('Competitor offer observation price must have at most two decimal places.');
        }
    }
}
