<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PayrollCalculationRunInput extends Model
{
    protected $fillable = [
        'payroll_calculation_run_id', 'employee_contract_payroll_input_id', 'segment_start',
        'segment_end', 'segment_days', 'period_days', 'input_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'segment_start' => 'date',
            'segment_end' => 'date',
            'segment_days' => 'integer',
            'period_days' => 'integer',
            'input_snapshot' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Payroll run input links are immutable. Create a new calculation run for changed inputs.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Payroll run input links cannot be deleted.');
        });
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollCalculationRun::class, 'payroll_calculation_run_id');
    }

    public function payrollInput(): BelongsTo
    {
        return $this->belongsTo(EmployeeContractPayrollInput::class, 'employee_contract_payroll_input_id');
    }
}
