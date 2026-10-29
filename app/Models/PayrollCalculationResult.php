<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PayrollCalculationResult extends Model
{
    protected $fillable = [
        'payroll_calculation_run_id', 'result_status', 'currency_code', 'gross_amount',
        'deductions_amount', 'net_amount', 'result_snapshot', 'snapshot_hash', 'snapshotted_at',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'deductions_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'result_snapshot' => 'array',
            'snapshotted_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Payroll result snapshots are immutable. Create a new calculation run for corrections.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Payroll result snapshots cannot be deleted.');
        });
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollCalculationRun::class, 'payroll_calculation_run_id');
    }
}
