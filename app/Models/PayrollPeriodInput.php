<?php

namespace App\Models;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class PayrollPeriodInput extends Model
{
    use LogsActivity;

    public const CATEGORIES = ['leave', 'absence', 'overtime', 'allowance', 'bonus', 'earning', 'deduction', 'other'];

    public const STATUSES = ['draft', 'submitted', 'approved', 'rejected'];

    protected $fillable = [
        'employee_id', 'employee_contract_id', 'corrects_id', 'period_start', 'period_end', 'effective_from', 'effective_until',
        'category', 'entry_type', 'description', 'quantity', 'unit', 'amount', 'currency_code',
        'source_type', 'source_reference', 'notes', 'status', 'recorded_by', 'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date', 'period_end' => 'date', 'effective_from' => 'date', 'effective_until' => 'date',
            'quantity' => 'decimal:6', 'amount' => 'decimal:6', 'reviewed_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $input): void {
            $input->recorded_by ??= auth()->id();
            $input->status ??= 'draft';
        });
        static::saving(function (self $input): void {
            $input->status ??= 'draft';
            $originalStatus = $input->exists ? $input->getOriginal('status') : null;
            if ($originalStatus === 'approved') {
                throw new LogicException('Approved payroll period inputs are immutable. Record a correction instead.');
            }
            if ($originalStatus === 'submitted') {
                $changed = array_diff(array_keys($input->getDirty()), ['status', 'reviewed_by', 'reviewed_at', 'review_note', 'updated_at']);
                if ($changed !== []) {
                    throw new LogicException('Submitted payroll period inputs are locked until the review decision.');
                }
            }
            if ($input->status !== $originalStatus && in_array($input->status, ['submitted', 'approved', 'rejected'], true)) {
                $ability = $input->status === 'submitted' ? 'payroll_period_input.submit' : 'payroll_period_input.approve';
                $actor = auth()->user();
                $authorized = $actor && Gate::forUser($actor)->allows($ability);
                if (! $authorized && auth()->id() !== null && function_exists('hexa')) {
                    $authorized = hexa()->can($ability);
                }
                if (! $authorized) {
                    throw new AuthorizationException('The required payroll period input permission is missing: '.$ability);
                }
                if (in_array($input->status, ['approved', 'rejected'], true) && (int) $input->reviewed_by !== (int) auth()->id()) {
                    throw ValidationException::withMessages(['reviewed_by' => 'The reviewer must be the authenticated actor.']);
                }
            }
            $contract = EmployeeContract::query()->find($input->employee_contract_id);
            $errors = [];
            if (! $contract || (int) $contract->employee_id !== (int) $input->employee_id) {
                $errors['employee_contract_id'] = 'Select a contract belonging to the selected employee.';
            }
            if ($contract && $input->effective_from && $contract->start_date && Carbon::parse((string) $input->effective_from)->lt(Carbon::parse((string) $contract->start_date))) {
                $errors['effective_from'] = 'The input cannot start before the linked contract.';
            }
            if ($contract && $input->effective_until && $contract->end_date && Carbon::parse((string) $input->effective_until)->gt(Carbon::parse((string) $contract->end_date))) {
                $errors['effective_until'] = 'The input cannot extend beyond the linked contract.';
            }
            foreach (['period_start', 'period_end', 'effective_from'] as $field) {
                if (! $input->{$field}) {
                    $errors[$field] = 'This date is required.';
                }
            }
            if ($input->period_start && $input->period_end && (Carbon::parse((string) $input->period_end)->lt(Carbon::parse((string) $input->period_start)) || Carbon::parse((string) $input->period_start)->day !== 1 || ! Carbon::parse((string) $input->period_end)->isLastOfMonth() || Carbon::parse((string) $input->period_start)->format('Y-m') !== Carbon::parse((string) $input->period_end)->format('Y-m'))) {
                $errors['period_end'] = 'Payroll inputs must belong to one complete calendar month.';
            }
            if ($input->effective_from && $input->effective_until && Carbon::parse((string) $input->effective_until)->lt(Carbon::parse((string) $input->effective_from))) {
                $errors['effective_until'] = 'The effective end cannot precede the start.';
            }
            if ($input->employee_id && $input->period_start && EmployeePaySlip::query()
                ->where('employee_id', $input->employee_id)
                ->whereDate('month', Carbon::parse((string) $input->period_start)->startOfMonth()->toDateString())
                ->where(fn ($query) => $query->whereIn('status', ['Closed', 'Paid'])->orWhere('approval_status', '!=', 'draft'))
                ->exists()) {
                $errors['period_start'] = 'The payroll period is under review or locked. Record a correction in a later open period.';
            }
            if ($input->effective_from && $input->period_start && Carbon::parse((string) $input->effective_from)->lt(Carbon::parse((string) $input->period_start))) {
                $errors['effective_from'] = 'The effective date must be inside the payroll period.';
            }
            if ($input->effective_until && $input->period_end && Carbon::parse((string) $input->effective_until)->gt(Carbon::parse((string) $input->period_end))) {
                $errors['effective_until'] = 'The effective date must be inside the payroll period.';
            }
            if (! in_array($input->category, self::CATEGORIES, true)) {
                $errors['category'] = 'Choose a supported payroll input category.';
            }
            if (! in_array($input->entry_type, ['earning', 'deduction', 'informational'], true)) {
                $errors['entry_type'] = 'Choose a supported entry type.';
            }
            if (! in_array($input->status, self::STATUSES, true)) {
                $errors['status'] = 'Choose a supported status.';
            }
            if ($input->amount !== null && (float) $input->amount < 0) {
                $errors['amount'] = 'Enter a non-negative amount; entry type determines its meaning.';
            }
            if ($input->quantity !== null && (float) $input->quantity < 0) {
                $errors['quantity'] = 'Quantity cannot be negative.';
            }
            if ($input->currency_code !== null && ! preg_match('/^[A-Z]{3}$/D', $input->currency_code)) {
                $errors['currency_code'] = 'Use a three-letter uppercase currency code.';
            }
            if ($input->amount !== null && $input->currency_code === null) {
                $errors['currency_code'] = 'An explicit currency is required when an amount is recorded.';
            }
            if ($input->status === 'approved' && (! $input->reviewed_by || ! $input->reviewed_at)) {
                $errors['status'] = 'Approval requires a reviewer and timestamp.';
            }
            if ($input->status === 'submitted' && (! $input->source_type || ! $input->source_reference)) {
                $errors['source_reference'] = 'Submitted inputs require a source type and source reference.';
            }
            if ($input->employee_contract_id && $input->period_start && $input->source_type && $input->source_reference) {
                $duplicate = self::query()->where('employee_contract_id', $input->employee_contract_id)->whereDate('period_start', Carbon::parse((string) $input->period_start)->toDateString())->where('source_type', $input->source_type)->where('source_reference', $input->source_reference)->when($input->exists, fn ($query) => $query->whereKeyNot($input->getKey()))->exists();
                if ($duplicate) {
                    $errors['source_reference'] = 'This source record is already linked to an input for the payroll period.';
                }
            }
            if ($input->employee_contract_id && in_array($input->category, ['leave', 'absence'], true) && $input->effective_from) {
                $overlapEnd = $input->effective_until ? Carbon::parse((string) $input->effective_until)->toDateString() : Carbon::parse((string) $input->period_end)->toDateString();
                $overlap = self::query()->where('employee_contract_id', $input->employee_contract_id)
                    ->whereIn('category', ['leave', 'absence'])->whereIn('status', ['draft', 'submitted', 'approved'])
                    ->whereDate('effective_from', '<=', $overlapEnd)
                    ->where(fn ($query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', Carbon::parse((string) $input->effective_from)->toDateString()))
                    ->when($input->exists, fn ($query) => $query->whereKeyNot($input->getKey()))
                    ->when($input->corrects_id, fn ($query) => $query->whereKeyNot($input->corrects_id))->exists();
                if ($overlap) {
                    $errors['effective_from'] = 'Leave and absence inputs cannot overlap for the same contract.';
                }
            }
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
        });
        static::deleting(static function (): never {
            throw new LogicException('Payroll period input history is retained; use a correction entry instead.');
        });
    }

    public function scopeForPeriod(Builder $query, int $contractId, string $start, string $end): Builder
    {
        return $query->where('employee_contract_id', $contractId)->whereDate('period_start', $start)->whereDate('period_end', $end);
    }

    public function correctionOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'corrects_id');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(self::class, 'corrects_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
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
        return LogOptions::defaults()->logOnly(['employee_id', 'employee_contract_id', 'corrects_id', 'period_start', 'period_end', 'effective_from', 'effective_until', 'category', 'entry_type', 'status', 'recorded_by', 'reviewed_by', 'reviewed_at'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
