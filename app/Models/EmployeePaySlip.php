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
            'reviewed_at' => 'immutable_datetime',
            'review_snapshot' => 'array',
            'approved_at' => 'immutable_datetime',
            'approved_snapshot' => 'array',
            'closed_at' => 'immutable_datetime',
            'lifecycle_version' => 'integer',
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
                $paySlip->approval_status ??= 'draft';

                if ($paySlip->status !== 'Open') {
                    throw ValidationException::withMessages(['status' => 'New payslips must start in Open status.']);
                }
                if ($paySlip->approval_status !== 'draft') {
                    throw ValidationException::withMessages(['approval_status' => 'New payslips must start in draft approval state.']);
                }
            } else {
                if ($paySlip->isDirty(['employee_id', 'month'])) {
                    throw ValidationException::withMessages(['month' => 'The employee and pay period are immutable after a payslip is created.']);
                }

                $previousStatus = (string) $paySlip->getOriginal('status');
                $nextStatus = (string) $paySlip->status;
                $allowedTransitions = [
                    'Open' => ['Open', 'Closed'],
                    'Closed' => ['Closed'],
                    'Paid' => ['Paid'],
                ];

                if ($paySlip->isDirty('status') && ! in_array($nextStatus, $allowedTransitions[$previousStatus] ?? [$previousStatus], true)) {
                    throw ValidationException::withMessages(['status' => "The payslip cannot transition from {$previousStatus} to {$nextStatus}."]);
                }
                if ($paySlip->isDirty('status') && $nextStatus === 'Paid' && $previousStatus !== 'Paid') {
                    throw ValidationException::withMessages(['paid_on' => 'Payslips cannot be marked Paid until verified payment evidence is integrated. A date alone is not payment evidence.']);
                }
                if ($paySlip->isDirty('status') && $nextStatus === 'Closed'
                    && ($paySlip->approval_status !== 'closed' || ! $paySlip->closed_by || ! $paySlip->closed_at || ! $paySlip->approved_snapshot_hash)) {
                    throw ValidationException::withMessages(['status' => 'Close a payslip through the authorized payroll period lifecycle service after approval.']);
                }

                $lifecycleFields = [
                    'approval_status', 'reviewed_by', 'reviewed_at', 'review_snapshot', 'review_snapshot_hash',
                    'approved_by', 'approved_at', 'approved_snapshot', 'approved_snapshot_hash',
                    'closed_by', 'closed_at', 'lifecycle_version',
                ];
                if ($paySlip->isDirty($lifecycleFields) && ! $paySlip->isDirty('approval_status')) {
                    throw ValidationException::withMessages(['approval_status' => 'Payroll approval metadata can only change through the lifecycle service.']);
                }
                if ($paySlip->isDirty('approval_status')) {
                    $from = (string) $paySlip->getOriginal('approval_status');
                    $to = (string) $paySlip->approval_status;
                    $allowedApprovalTransitions = ['draft' => ['in_review'], 'in_review' => ['approved'], 'approved' => ['closed']];
                    if (! in_array($to, $allowedApprovalTransitions[$from] ?? [], true)) {
                        throw ValidationException::withMessages(['approval_status' => "The payroll approval state cannot transition from {$from} to {$to}."]);
                    }
                    if ($to === 'in_review' && ($paySlip->status !== 'Open' || ! $paySlip->reviewed_by || ! $paySlip->reviewed_at || ! $paySlip->review_snapshot || ! $paySlip->review_snapshot_hash)) {
                        throw ValidationException::withMessages(['review_snapshot' => 'Submission requires an open period, reviewer, timestamp, and immutable review snapshot.']);
                    }
                    if ($to === 'approved' && ($paySlip->status !== 'Open' || ! $paySlip->approved_by || ! $paySlip->approved_at || ! $paySlip->approved_snapshot || ! $paySlip->approved_snapshot_hash || $paySlip->approved_snapshot_hash !== $paySlip->review_snapshot_hash || (int) $paySlip->approved_by === (int) $paySlip->reviewed_by)) {
                        throw ValidationException::withMessages(['approved_snapshot' => 'Approval requires a distinct approver and a snapshot matching the reviewed version.']);
                    }
                    if ($to === 'closed' && ($paySlip->status !== 'Closed' || ! $paySlip->closed_by || ! $paySlip->closed_at || $paySlip->approved_snapshot_hash !== $paySlip->review_snapshot_hash)) {
                        throw ValidationException::withMessages(['closed_at' => 'Closing requires an approved immutable snapshot and a recorded closer.']);
                    }
                    if ((int) $paySlip->lifecycle_version !== (int) $paySlip->getOriginal('lifecycle_version') + 1) {
                        throw ValidationException::withMessages(['lifecycle_version' => 'Each payroll lifecycle transition must advance its version exactly once.']);
                    }
                }
                foreach (['review_snapshot', 'review_snapshot_hash', 'approved_snapshot', 'approved_snapshot_hash'] as $snapshotField) {
                    if ($paySlip->getOriginal($snapshotField) !== null && $paySlip->isDirty($snapshotField)) {
                        throw ValidationException::withMessages([$snapshotField => 'Payroll review and approval snapshots are immutable.']);
                    }
                }

                if ($previousStatus === 'Paid' && $paySlip->isDirty(['employee_id', 'month', 'manual_amount', 'items', 'amount', 'due_on', 'paid_on', 'status'])) {
                    throw ValidationException::withMessages(['status' => 'A paid payslip is locked. Record a correction as an adjustment on a later open payslip.']);
                }

                if (($previousStatus !== 'Open' || $paySlip->approval_status !== 'draft') && $paySlip->isDirty(['manual_amount', 'items', 'amount', 'due_on'])) {
                    throw ValidationException::withMessages(['status' => 'A reviewed, approved, closed, or paid payslip is locked. Use the documented correction workflow.']);
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
            if (! $paySlip->isOpen() || $paySlip->approval_status !== 'draft' || $paySlip->lineItems()->exists()) {
                throw ValidationException::withMessages(['status' => 'Payslips with source items or a locked status cannot be deleted.']);
            }
        });
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return HasMany<EmployeePaySlipItem, $this> */
    public function lineItems(): HasMany
    {
        return $this->hasMany(EmployeePaySlipItem::class, 'employee_pay_slip_id');
    }

    /** @return HasMany<PayrollCalculationRun, $this> */
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
        return $this->status === 'Open' && ($this->approval_status ?? 'draft') === 'draft';
    }

    /** @return HasMany<PayrollPeriodLifecycleEvent, $this> */
    public function lifecycleEvents(): HasMany
    {
        return $this->hasMany(PayrollPeriodLifecycleEvent::class, 'employee_pay_slip_id')->orderBy('created_at')->orderBy('id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function recalculateAmount(): void
    {
        if (! $this->isOpen()) {
            throw ValidationException::withMessages(['status' => 'Only a draft, open payslip can be recalculated.']);
        }

        $total = round((float) $this->manual_amount + (float) $this->lineItems()->sum('amount'), 2, PHP_ROUND_HALF_UP);
        $this->forceFill(['amount' => $total])->saveQuietly();
    }
}
