<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property array<string, mixed> $assumptions
 * @property-read CashForecast $forecast
 * @property-read Collection<int, CashForecastEntry> $entries
 */
class CashForecastScenario extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_RETIRED = 'retired';

    protected $fillable = [
        'cash_forecast_id', 'scenario_name', 'version', 'period_start', 'period_end',
        'currency_code', 'opening_balance', 'assumptions', 'status', 'created_by',
        'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
            'opening_balance' => 'decimal:2',
            'assumptions' => 'array',
            'approved_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $scenario): void {
            if ($scenario->status !== self::STATUS_DRAFT) {
                throw new LogicException('Cash forecast scenarios must be created as drafts.');
            }
        });

        static::saving(function (self $scenario): void {
            if (! preg_match('/^[A-Z]{3}$/D', (string) $scenario->currency_code)) {
                throw ValidationException::withMessages(['currency_code' => 'Currency must be an uppercase three-letter ISO code.']);
            }
            if ($scenario->period_end->lt($scenario->period_start)) {
                throw ValidationException::withMessages(['period_end' => 'Forecast end must be on or after its start.']);
            }
        });

        static::updating(function (self $scenario): void {
            $originalStatus = $scenario->getOriginal('status');
            $persistedStatus = static::query()->whereKey($scenario->getKey())->value('status');
            if ((string) $persistedStatus !== (string) $originalStatus) {
                throw new LogicException('Cash forecast scenario state changed. Refresh before editing.');
            }
            if ($originalStatus === self::STATUS_DRAFT) {
                return;
            }
            $allowed = $originalStatus === self::STATUS_APPROVED
                ? ['status', 'updated_at']
                : [];
            if (array_diff(array_keys($scenario->getDirty()), $allowed) !== []) {
                throw new LogicException('Approved and retired cash forecast scenarios are immutable. Create a new version.');
            }
            if ($originalStatus === self::STATUS_APPROVED && $scenario->status !== self::STATUS_RETIRED) {
                throw new LogicException('An approved scenario can only be retired.');
            }
            if ($originalStatus === self::STATUS_RETIRED) {
                throw new LogicException('Retired cash forecast scenarios are immutable.');
            }
        });

        static::deleting(fn () => throw new LogicException('Cash forecast scenarios are retained as history and cannot be deleted.'));
    }

    /** @return BelongsTo<CashForecast, $this> */
    public function forecast(): BelongsTo
    {
        return $this->belongsTo(CashForecast::class, 'cash_forecast_id');
    }

    /** @return HasMany<CashForecastEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(CashForecastEntry::class)->orderBy('expected_on')->orderBy('id');
    }

    /** @return HasMany<CashForecastRun, $this> */
    public function runs(): HasMany
    {
        return $this->hasMany(CashForecastRun::class, 'cash_forecast_scenario_id')->latest('created_at');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
