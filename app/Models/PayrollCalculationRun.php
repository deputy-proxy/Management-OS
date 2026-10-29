<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class PayrollCalculationRun extends Model
{
    use HasFactory;

    public const STATUS_BLOCKED = 'blocked';

    public const STATUS_READY = 'ready_for_calculation';

    public const STATUS_CALCULATED = 'calculated';

    protected $fillable = [
        'employee_id', 'employee_contract_id', 'employee_contract_payroll_input_id', 'payroll_policy_id',
        'employee_pay_slip_id', 'period_start', 'period_end', 'idempotency_key', 'request_fingerprint',
        'run_status', 'blocked_reasons', 'input_snapshot', 'policy_snapshot', 'calculation_version', 'initiated_by',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'blocked_reasons' => 'array',
            'input_snapshot' => 'array',
            'policy_snapshot' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Payroll calculation runs are immutable. Create a new run for changed inputs.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Payroll calculation runs are retained as audit records and cannot be deleted.');
        });
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function employeeContract(): BelongsTo
    {
        return $this->belongsTo(EmployeeContract::class);
    }

    public function payrollInput(): BelongsTo
    {
        return $this->belongsTo(EmployeeContractPayrollInput::class, 'employee_contract_payroll_input_id');
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(PayrollPolicy::class, 'payroll_policy_id');
    }

    public function paySlip(): BelongsTo
    {
        return $this->belongsTo(EmployeePaySlip::class, 'employee_pay_slip_id');
    }

    public function inputVersions(): HasMany
    {
        return $this->hasMany(PayrollCalculationRunInput::class)->orderBy('segment_start');
    }

    public function result(): HasOne
    {
        return $this->hasOne(PayrollCalculationResult::class, 'payroll_calculation_run_id');
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }
}
