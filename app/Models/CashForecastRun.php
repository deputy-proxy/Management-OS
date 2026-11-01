<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property Carbon $as_of
 * @property array<string, mixed> $report_snapshot
 * @property Carbon $created_at
 */
class CashForecastRun extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'cash_forecast_scenario_id', 'as_of', 'report_snapshot', 'generated_by', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'as_of' => 'date',
            'report_snapshot' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Cash forecast run snapshots are immutable.'));
        static::deleting(fn () => throw new LogicException('Cash forecast run snapshots are retained as history and cannot be deleted.'));
    }

    /** @return BelongsTo<CashForecastScenario, $this> */
    public function scenario(): BelongsTo
    {
        return $this->belongsTo(CashForecastScenario::class, 'cash_forecast_scenario_id');
    }

    /** @return BelongsTo<User, $this> */
    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
