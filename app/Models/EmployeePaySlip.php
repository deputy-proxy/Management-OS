<?php

namespace App\Models;

use App\Models\Concerns\HasPanelSearchTitle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

class EmployeePaySlip extends Model
{
    use HasFactory;
    use HasPanelSearchTitle;
    use SoftDeletes;

    protected $fillable = ['employee_id', 'month', 'items', 'manual_amount', 'due_on', 'paid_on', 'status'];

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'items' => 'array',
            'manual_amount' => 'decimal:2',
            'amount' => 'decimal:2',
            'due_on' => 'date',
            'paid_on' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (EmployeePaySlip $paySlip): void {
            if (! $paySlip->exists) {
                if (! $paySlip->month) {
                    throw ValidationException::withMessages(['month' => 'A pay period is required.']);
                }

                $paySlip->month = Carbon::parse($paySlip->month)->startOfMonth()->toDateString();
                $paySlip->status ??= 'Open';

                if ($paySlip->status !== 'Open') {
                    throw ValidationException::withMessages(['status' => 'New payslips must start in Open status.']);
                }
            } else {
                if ($paySlip->isDirty(['employee_id', 'month'])) {
                    throw ValidationException::withMessages(['month' => 'The employee and pay period are immutable after a payslip is created.']);
                }

                $previousStatus = (string) $paySlip->getOriginal('status');
                $nextStatus = (string) $paySlip->status;
                $allowedTransitions = [
                    'Open' => ['Open', 'Closed'],
                    'Closed' => ['Closed', 'Paid'],
                    'Paid' => ['Paid'],
                ];

                if ($paySlip->isDirty('status') && ! in_array($nextStatus, $allowedTransitions[$previousStatus] ?? [$previousStatus], true)) {
                    throw ValidationException::withMessages(['status' => "The payslip cannot transition from {$previousStatus} to {$nextStatus}."]);
                }

                if ($previousStatus === 'Paid' && $paySlip->isDirty(['employee_id', 'month', 'manual_amount', 'items', 'amount', 'due_on', 'paid_on', 'status'])) {
                    throw ValidationException::withMessages(['status' => 'A paid payslip is locked. Record a correction as an adjustment on a later open payslip.']);
                }

                if ($previousStatus !== 'Open' && $paySlip->isDirty(['manual_amount', 'items'])) {
                    throw ValidationException::withMessages(['status' => 'A closed or paid payslip cannot be recalculated. Use an adjustment on a later open payslip.']);
                }
            }

            if ($paySlip->status === 'Paid' && ! $paySlip->paid_on) {
                throw ValidationException::withMessages(['paid_on' => 'A payment date is required before marking a payslip as Paid.']);
            }

            if ($paySlip->status !== 'Paid' && $paySlip->paid_on && ($paySlip->isDirty('paid_on') || $paySlip->isDirty('status'))) {
                throw ValidationException::withMessages(['paid_on' => 'A payment date can only be set when the payslip is Paid.']);
            }

            if ($paySlip->isDirty('manual_amount') || ! $paySlip->exists) {
                $lineTotal = $paySlip->exists ? (float) $paySlip->lineItems()->sum('amount') : 0.0;
                $paySlip->amount = round((float) ($paySlip->manual_amount ?? 0) + $lineTotal, 2, PHP_ROUND_HALF_UP);
            }
        });

        static::deleting(function (EmployeePaySlip $paySlip): void {
            if (! $paySlip->isOpen() || $paySlip->lineItems()->exists()) {
                throw ValidationException::withMessages(['status' => 'Payslips with source items or a locked status cannot be deleted.']);
            }
        });
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(EmployeePaySlipItem::class, 'employee_pay_slip_id');
    }

    public function calculationRuns(): HasMany
    {
        return $this->hasMany(PayrollCalculationRun::class, 'employee_pay_slip_id')->latest('id');
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    public function isOpen(): bool
    {
        return $this->status === 'Open';
    }

    public function recalculateAmount(): void
    {
        $total = round((float) $this->manual_amount + (float) $this->lineItems()->sum('amount'), 2, PHP_ROUND_HALF_UP);
        $this->forceFill(['amount' => $total])->saveQuietly();
    }
}
