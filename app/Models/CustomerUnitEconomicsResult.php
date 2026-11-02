<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CustomerUnitEconomicsResult extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'acquisition_cost_amount' => 'decimal:2',
            'cac_amount' => 'decimal:2',
            'revenue_amount' => 'decimal:2',
            'ltv_amount' => 'decimal:2',
            'source_snapshot' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Customer unit-economics results are immutable.'));
        static::deleting(fn () => throw new LogicException('Customer unit-economics results are immutable.'));
    }

    /** @return BelongsTo<CustomerUnitEconomicsRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(CustomerUnitEconomicsRun::class, 'customer_unit_economics_run_id');
    }
}
