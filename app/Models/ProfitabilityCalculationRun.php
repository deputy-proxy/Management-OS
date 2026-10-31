<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * @property array<string, mixed> $assumptions
 * @property array<int, array<string, mixed>> $exceptions
 */
class ProfitabilityCalculationRun extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'assumptions' => 'array',
            'exceptions' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Profitability calculation runs are immutable.'));
        static::deleting(fn () => throw new LogicException('Profitability calculation runs are immutable.'));
    }

    /** @return HasMany<ProfitabilityCalculationResult, $this> */
    public function results(): HasMany
    {
        return $this->hasMany(ProfitabilityCalculationResult::class);
    }
}
