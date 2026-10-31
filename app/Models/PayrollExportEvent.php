<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PayrollExportEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'employee_pay_slip_id', 'payroll_calculation_run_id', 'actor_id', 'report_type',
        'format', 'row_count', 'approved_snapshot_hash', 'content_sha256', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'row_count' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Payroll export audit records are immutable.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Payroll export audit records cannot be deleted.');
        });
    }

    public function paySlip(): BelongsTo
    {
        return $this->belongsTo(EmployeePaySlip::class, 'employee_pay_slip_id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollCalculationRun::class, 'payroll_calculation_run_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
