<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class PayrollException extends Model
{
    public const SEVERITY_BLOCKING = 'blocking';

    public const SEVERITY_WARNING = 'warning';

    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED = 'resolved';

    protected $fillable = [
        'payroll_calculation_run_id', 'employee_pay_slip_id', 'employee_contract_id', 'employee_id',
        'period_start', 'period_end', 'exception_key', 'code', 'severity', 'status', 'message',
        'resolution_guidance', 'affected_records', 'detected_by', 'detected_at', 'resolved_by',
        'resolved_at', 'resolution_note',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'affected_records' => 'array',
            'detected_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (self $exception): void {
            $allowed = ['status', 'resolved_by', 'resolved_at', 'resolution_note', 'updated_at'];
            if (array_diff(array_keys($exception->getDirty()), $allowed) !== []) {
                throw new LogicException('Payroll exception evidence is immutable. Only its resolution state may change.');
            }
            if ($exception->getOriginal('status') !== self::STATUS_OPEN || $exception->status !== self::STATUS_RESOLVED) {
                throw new LogicException('Only open payroll exceptions can be resolved.');
            }
        });
        static::deleting(static function (): never {
            throw new LogicException('Payroll exceptions are retained as audit records and cannot be deleted.');
        });
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollCalculationRun::class, 'payroll_calculation_run_id');
    }

    public function paySlip(): BelongsTo
    {
        return $this->belongsTo(EmployeePaySlip::class, 'employee_pay_slip_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(EmployeeContract::class, 'employee_contract_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function detector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'detected_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PayrollExceptionEvent::class)->orderBy('created_at')->orderBy('id');
    }
}
