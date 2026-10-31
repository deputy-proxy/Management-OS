<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property array<string, mixed> $source_snapshot
 * @property string|null $revenue_amount
 * @property string|null $direct_cost_amount
 * @property string|null $overhead_cost_amount
 * @property string|null $provisional_profit_amount
 * @property string|null $provisional_margin_percent
 */
class ProfitabilityCalculationResult extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'revenue_amount' => 'decimal:2',
            'direct_cost_amount' => 'decimal:2',
            'overhead_cost_amount' => 'decimal:2',
            'provisional_profit_amount' => 'decimal:2',
            'provisional_margin_percent' => 'decimal:2',
            'source_snapshot' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Profitability calculation results are immutable.'));
        static::deleting(fn () => throw new LogicException('Profitability calculation results are immutable.'));
    }

    /** @return BelongsTo<ProfitabilityCalculationRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(ProfitabilityCalculationRun::class, 'profitability_calculation_run_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
