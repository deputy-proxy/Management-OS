<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class CustomerUnitEconomicsRun extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'policy_snapshot' => 'array',
            'exceptions' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Customer unit-economics runs are immutable.'));
        static::deleting(fn () => throw new LogicException('Customer unit-economics runs are immutable.'));
    }

    /** @return HasMany<CustomerUnitEconomicsResult, $this> */
    public function results(): HasMany
    {
        return $this->hasMany(CustomerUnitEconomicsResult::class);
    }
}
