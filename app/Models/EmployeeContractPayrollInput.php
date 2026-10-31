<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use LogicException;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class EmployeeContractPayrollInput extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    public const SALARY_BASES = [
        'monthly' => 'Monthly',
        'hourly' => 'Hourly',
        'daily' => 'Daily',
        'annual' => 'Annual',
        'other' => 'Other / configured',
    ];

    private const SENSITIVE_AUDIT_FIELDS = ['work_schedule', 'allowance_inputs', 'tax_status_code', 'source_reference'];

    /** @var list<string> */
    private array $sensitiveFieldsChanged = [];

    public const STATUSES = [
        'unverified' => 'Unverified',
        'verified' => 'Verified',
        'rejected' => 'Rejected',
    ];

    /** @var list<string> */
    protected $fillable = [
        'employee_contract_id', 'effective_from', 'effective_until', 'salary_basis', 'base_amount', 'currency_code',
        'scheduled_hours_per_week', 'work_schedule', 'allowance_inputs', 'tax_status_code',
        'source_reference', 'input_status', 'recorded_by', 'reviewed_by', 'reviewed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_until' => 'date',
            'base_amount' => 'decimal:6',
            'scheduled_hours_per_week' => 'decimal:2',
            'work_schedule' => 'array',
            'allowance_inputs' => 'array',
            'reviewed_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $input): void {
            $input->recorded_by ??= auth()->id();
            $input->input_status ??= 'unverified';
        });

        static::updating(function (self $input): void {
            if ($input->getOriginal('input_status') !== 'verified') {
                return;
            }

            $changedFields = array_diff(array_keys($input->getDirty()), ['effective_until', 'updated_at']);
            if ($changedFields !== []) {
                throw new LogicException('Verified payroll inputs are immutable; create a new effective-dated version instead.');
            }

            $originalEnd = $input->getOriginal('effective_until');
            if ($input->isDirty('effective_until') && $originalEnd !== null) {
                $newEnd = $input->effective_until;
                if ($newEnd === null || Carbon::parse((string) $newEnd)->gt(Carbon::parse((string) $originalEnd))) {
                    throw new LogicException('A verified payroll input period may only be shortened, never extended.');
                }
            }
        });

        static::saving(function (self $input): void {
            $errors = [];

            $contract = $input->employee_contract_id
                ? EmployeeContract::query()->find($input->employee_contract_id)
                : null;
            if (! $contract) {
                $errors['employee_contract_id'] = 'Select an existing employee contract.';
            }
            if (! $input->effective_from) {
                $errors['effective_from'] = 'An effective start date is required.';
            }
            if ($input->effective_from && $input->effective_until
                && Carbon::parse((string) $input->effective_until)->lt(Carbon::parse((string) $input->effective_from))) {
                $errors['effective_until'] = 'The effective end date cannot precede the start date.';
            }
            if ($contract && $input->effective_from && $contract->start_date
                && Carbon::parse((string) $input->effective_from)->lt(Carbon::parse((string) $contract->start_date))) {
                $errors['effective_from'] = 'Payroll inputs cannot start before the linked contract.';
            }
            if ($contract && $input->effective_from && $contract->end_date
                && Carbon::parse((string) $input->effective_from)->gt(Carbon::parse((string) $contract->end_date))) {
                $errors['effective_from'] = 'Payroll inputs cannot start after the linked contract ends.';
            }
            if ($contract && $input->effective_until && $contract->end_date
                && Carbon::parse((string) $input->effective_until)->gt(Carbon::parse((string) $contract->end_date))) {
                $errors['effective_until'] = 'Payroll inputs cannot extend beyond the linked contract.';
            }
            if ($input->salary_basis !== null && ! array_key_exists((string) $input->salary_basis, self::SALARY_BASES)) {
                $errors['salary_basis'] = 'Choose a supported salary basis.';
            }
            if ($input->base_amount !== null && (float) $input->base_amount < 0) {
                $errors['base_amount'] = 'Base amount must be a non-negative explicit amount.';
            }
            if ($input->currency_code !== null && ! preg_match('/^[A-Z]{3}$/', (string) $input->currency_code)) {
                $errors['currency_code'] = 'Use a three-letter uppercase currency code.';
            }
            if ($input->scheduled_hours_per_week !== null
                && ((float) $input->scheduled_hours_per_week < 0 || (float) $input->scheduled_hours_per_week > 168)) {
                $errors['scheduled_hours_per_week'] = 'Scheduled hours must be between 0 and 168 per week.';
            }
            if (! array_key_exists((string) $input->input_status, self::STATUSES)) {
                $errors['input_status'] = 'Choose a supported input status.';
            }
            $workSchedule = $input->getAttribute('work_schedule');
            if ($workSchedule !== null && ! is_array($workSchedule)) {
                $errors['work_schedule'] = 'Schedule details must be structured data.';
            }
            if (! self::hasValidAllowanceInputs($input->getAttribute('allowance_inputs'))) {
                $errors['allowance_inputs'] = 'Each allowance requires a code, non-negative amount, and three-letter uppercase currency code.';
            }
            if ($input->input_status === 'verified' && (! $input->reviewed_by || ! $input->reviewed_at)) {
                $errors['input_status'] = 'Verified inputs require a reviewer and review timestamp.';
            }

            $overlapQuery = self::query()->where('employee_contract_id', $input->employee_contract_id);
            if ($input->exists) {
                $overlapQuery->whereKeyNot($input->getKey());
            }
            $overlapEnd = $input->effective_until
                ? Carbon::parse((string) $input->effective_until)->toDateString()
                : ($contract?->end_date ? Carbon::parse((string) $contract->end_date)->toDateString() : '9999-12-31');
            $overlapStart = $input->effective_from
                ? Carbon::parse((string) $input->effective_from)->toDateString()
                : '0001-01-01';
            $overlap = $overlapQuery
                ->whereDate('effective_from', '<=', $overlapEnd)
                ->where(fn (Builder $query): Builder => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $overlapStart))
                ->exists();
            if ($overlap) {
                $errors['effective_from'] = 'Payroll input effective periods for one contract cannot overlap.';
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $input->sensitiveFieldsChanged = array_values(array_intersect(array_keys($input->getDirty()), self::SENSITIVE_AUDIT_FIELDS));
        });

        static::created(function (self $input): void {
            $input->recordSensitiveFieldChange();
        });

        static::updated(function (self $input): void {
            $input->recordSensitiveFieldChange();
        });

        static::forceDeleting(function (): never {
            throw new LogicException('Payroll input history is retained and cannot be permanently deleted.');
        });
    }

    private function recordSensitiveFieldChange(): void
    {
        $changedFields = $this->sensitiveFieldsChanged;
        if ($changedFields === []) {
            return;
        }

        activity('payroll-inputs')
            ->performedOn($this)
            ->causedBy(auth()->user())
            ->withProperties(['changed_fields' => $changedFields])
            ->log('Sensitive payroll input fields changed');
    }

    private static function hasValidAllowanceInputs(mixed $allowanceInputs): bool
    {
        if ($allowanceInputs === null) {
            return true;
        }
        if (! is_array($allowanceInputs)) {
            return false;
        }
        foreach ($allowanceInputs as $allowance) {
            if (! is_array($allowance)
                || ! is_string($allowance['code'] ?? null)
                || trim($allowance['code']) === ''
                || ! is_numeric($allowance['amount'] ?? null)
                || (float) $allowance['amount'] < 0
                || ! preg_match('/^[A-Z]{3}$/', (string) ($allowance['currency_code'] ?? ''))) {
                return false;
            }
        }

        return true;
    }

    /** @param Builder<EmployeeContractPayrollInput> $query */
    public function scopeEffectiveOn(Builder $query, string $date): Builder
    {
        return $query->whereDate('effective_from', '<=', $date)
            ->where(fn (Builder $inner): Builder => $inner->whereNull('effective_until')->orWhereDate('effective_until', '>=', $date));
    }

    public function isCompleteForCalculation(): bool
    {
        return $this->input_status === 'verified'
            && $this->salary_basis !== null
            && $this->base_amount !== null
            && $this->currency_code !== null
            && $this->scheduled_hours_per_week !== null;
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    public function calculationRuns(): HasMany
    {
        return $this->hasMany(PayrollCalculationRun::class, 'employee_contract_payroll_input_id');
    }

    public function employeeContract(): BelongsTo
    {
        return $this->belongsTo(EmployeeContract::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        // Keep explicit salary amounts, tax codes, source references, schedules, and allowance payloads out of activity-log values.
        return LogOptions::defaults()->logOnly([
            'employee_contract_id', 'effective_from', 'effective_until', 'salary_basis', 'currency_code',
            'scheduled_hours_per_week', 'input_status', 'recorded_by', 'reviewed_by', 'reviewed_at',
        ])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
