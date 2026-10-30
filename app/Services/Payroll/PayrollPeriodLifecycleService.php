<?php

namespace App\Services\Payroll;

use App\Models\EmployeePaySlip;
use App\Models\EmployeePaySlipItem;
use App\Models\PayrollCalculationRun;
use App\Models\PayrollCalculationRunInput;
use App\Models\PayrollPeriodLifecycleEvent;
use App\Models\User;
use App\Services\EmployeePaySlipService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PayrollPeriodLifecycleService
{
    public function submitForReview(User $actor, EmployeePaySlip $paySlip, ?string $note = null): EmployeePaySlip
    {
        $this->authorize($actor, 'payroll_period.review');
        $note = trim((string) $note);
        if (mb_strlen($note) > 2000) {
            throw ValidationException::withMessages(['review_note' => 'The review note cannot exceed 2000 characters.']);
        }

        return DB::transaction(function () use ($actor, $paySlip, $note): EmployeePaySlip {
            $locked = EmployeePaySlip::query()->whereKey($paySlip->getKey())->lockForUpdate()->firstOrFail();
            if (! $locked->isOpen() || $locked->approval_status !== 'draft') {
                throw ValidationException::withMessages(['approval_status' => 'Only draft, open payroll periods can be submitted for review.']);
            }
            $snapshot = $this->currentSnapshot($locked);
            if ((int) ($snapshot['initiated_by'] ?? 0) === (int) $actor->getKey()) {
                throw ValidationException::withMessages(['reviewed_by' => 'The payroll calculation initiator cannot review their own period.']);
            }
            $this->assertNoBlockingExceptions($locked);

            $locked->forceFill([
                'approval_status' => 'in_review',
                'reviewed_by' => $actor->getKey(),
                'reviewed_at' => now(),
                'review_snapshot' => $snapshot['data'],
                'review_snapshot_hash' => $snapshot['hash'],
                'lifecycle_version' => (int) $locked->lifecycle_version + 1,
            ])->save();
            $this->event($locked, $actor, 'submitted_for_review', 'draft', 'in_review', $note, $snapshot['hash']);

            return $locked->fresh(['lifecycleEvents']);
        });
    }

    public function approve(User $actor, EmployeePaySlip $paySlip, string $note): EmployeePaySlip
    {
        $this->authorize($actor, 'payroll_period.approve');
        $note = $this->requiredNote($note, 'approval_note');

        return DB::transaction(function () use ($actor, $paySlip, $note): EmployeePaySlip {
            $locked = EmployeePaySlip::query()->whereKey($paySlip->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'Open' || $locked->approval_status !== 'in_review') {
                throw ValidationException::withMessages(['approval_status' => 'Only periods currently in review can be approved.']);
            }
            $snapshot = $this->currentSnapshot($locked);
            if ((int) $locked->reviewed_by === (int) $actor->getKey() || (int) ($snapshot['initiated_by'] ?? 0) === (int) $actor->getKey()) {
                throw ValidationException::withMessages(['approved_by' => 'The approver must be different from both the calculation initiator and reviewer.']);
            }
            $this->assertNoBlockingExceptions($locked);
            if (! hash_equals((string) $locked->review_snapshot_hash, $snapshot['hash'])) {
                throw ValidationException::withMessages(['review_snapshot' => 'Payroll inputs or calculation versions changed after review. Submit the current period for review again.']);
            }

            $locked->forceFill([
                'approval_status' => 'approved',
                'approved_by' => $actor->getKey(),
                'approved_at' => now(),
                'approved_snapshot' => $snapshot['data'],
                'approved_snapshot_hash' => $snapshot['hash'],
                'lifecycle_version' => (int) $locked->lifecycle_version + 1,
            ])->save();
            $this->event($locked, $actor, 'approved', 'in_review', 'approved', $note, $snapshot['hash']);

            return $locked->fresh(['lifecycleEvents']);
        });
    }

    public function close(User $actor, EmployeePaySlip $paySlip, string $note): EmployeePaySlip
    {
        $this->authorize($actor, 'payroll_period.close');
        $note = $this->requiredNote($note, 'close_note');

        return DB::transaction(function () use ($actor, $paySlip, $note): EmployeePaySlip {
            $locked = EmployeePaySlip::query()->whereKey($paySlip->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'Open' || $locked->approval_status !== 'approved') {
                throw ValidationException::withMessages(['approval_status' => 'Only approved, open payroll periods can be closed.']);
            }
            $snapshot = $this->currentSnapshot($locked);
            $this->assertNoBlockingExceptions($locked);
            if (! hash_equals((string) $locked->approved_snapshot_hash, $snapshot['hash'])) {
                throw ValidationException::withMessages(['approved_snapshot' => 'The approved payroll snapshot no longer matches current records. Reopen review before closing.']);
            }

            $locked->forceFill([
                'status' => 'Closed',
                'approval_status' => 'closed',
                'closed_by' => $actor->getKey(),
                'closed_at' => now(),
                'lifecycle_version' => (int) $locked->lifecycle_version + 1,
            ])->save();
            $this->event($locked, $actor, 'closed', 'approved', 'closed', $note, $snapshot['hash']);

            return $locked->fresh(['lifecycleEvents']);
        });
    }

    /** Record a correction against a closed period without changing its approved snapshot. */
    public function recordCorrection(User $actor, EmployeePaySlip $target, EmployeePaySlipItem $originalItem, float $amount, string $reason): array
    {
        return $this->recordSignedAdjustment($actor, $target, $originalItem, $amount, $reason, 'correction_recorded');
    }

    private function recordSignedAdjustment(
        User $actor,
        EmployeePaySlip $target,
        EmployeePaySlipItem $originalItem,
        float $amount,
        string $reason,
        string $eventType,
    ): array {
        $this->authorize($actor, 'payroll_period.correct');
        $reason = $this->requiredNote($reason, $eventType === 'reversal_recorded' ? 'reversal_reason' : 'correction_reason');

        return DB::transaction(function () use ($actor, $target, $originalItem, $amount, $reason, $eventType): array {
            $lockedTarget = EmployeePaySlip::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();
            $original = EmployeePaySlip::query()->whereKey($originalItem->employee_pay_slip_id)->lockForUpdate()->firstOrFail();
            $sameOpenPeriod = (int) $lockedTarget->getKey() === (int) $original->getKey() && $original->isOpen();
            $closedHistoricalPeriod = in_array($original->approval_status, ['closed', 'legacy_closed', 'legacy_paid'], true)
                && in_array($original->status, ['Closed', 'Paid'], true);
            if (! $sameOpenPeriod && ! $closedHistoricalPeriod) {
                throw ValidationException::withMessages(['original_item_id' => 'A correction must reference an item from the current draft period or a closed historical period.']);
            }
            if ((int) $lockedTarget->employee_id !== (int) $original->employee_id
                || ! $lockedTarget->isOpen()
                || (! $sameOpenPeriod && ! Carbon::parse((string) $lockedTarget->month)->greaterThan(Carbon::parse((string) $original->month)))) {
                throw ValidationException::withMessages(['employee_pay_slip_id' => 'A correction must stay on its original open period or be carried to a later open period for the same employee.']);
            }

            $result = app(EmployeePaySlipService::class)->addAdjustment($actor, $lockedTarget, $originalItem, $amount, $reason);
            if ($result['created']) {
                $this->event($original, $actor, $eventType, $original->approval_status, $original->approval_status, $reason, $original->approved_snapshot_hash, [
                    'original_item_id' => (int) $originalItem->getKey(),
                    'target_pay_slip_id' => (int) $lockedTarget->getKey(),
                    'adjustment_item_id' => (int) $result['item']->getKey(),
                    'signed_amount' => (string) $result['item']->amount,
                ], $lockedTarget, $result['item']);
            }

            return $result;
        });
    }

    /** Reverse one immutable source item by adding its exact opposite amount to a later open period. */
    public function reverse(User $actor, EmployeePaySlip $target, EmployeePaySlipItem $originalItem, string $reason): array
    {
        $reason = $this->requiredNote($reason, 'reversal_reason');
        $amount = round(-1 * (float) $originalItem->amount, 2, PHP_ROUND_HALF_UP);
        if ($amount === 0.0) {
            throw ValidationException::withMessages(['original_item_id' => 'A zero-value source item cannot be reversed.']);
        }

        return $this->recordSignedAdjustment($actor, $target, $originalItem, $amount, 'Reversal: '.$reason, 'reversal_recorded');
    }

    /** @return array{data: array<string, mixed>, hash: string, initiated_by: int|null} */
    private function currentSnapshot(EmployeePaySlip $paySlip): array
    {
        $record = EmployeePaySlip::query()->with([
            'lineItems' => fn ($query) => $query->orderBy('id'),
            'calculationRuns' => fn ($query) => $query->orderBy('id'),
            'calculationRuns.inputVersions',
            'calculationRuns.result.items',
        ])->findOrFail($paySlip->getKey());

        $latestRun = PayrollCalculationRun::query()
            ->with('result')
            ->where('employee_pay_slip_id', $record->getKey())
            ->orderByDesc('id')
            ->first();
        if (! $latestRun
            || (int) $latestRun->employee_id !== (int) $record->employee_id
            || Carbon::parse((string) $latestRun->period_start)->toDateString() !== Carbon::parse((string) $record->month)->startOfMonth()->toDateString()
            || Carbon::parse((string) $latestRun->period_end)->toDateString() !== Carbon::parse((string) $record->month)->endOfMonth()->toDateString()
            || $latestRun->run_status !== PayrollCalculationRun::STATUS_CALCULATED
            || $latestRun->result?->result_status !== 'calculated') {
            throw ValidationException::withMessages(['calculation_run' => 'The latest payroll calculation run must be successfully calculated before review.']);
        }

        $runs = $record->calculationRuns->map(fn (PayrollCalculationRun $run): array => [
            'id' => (int) $run->getKey(),
            'initiated_by' => $run->initiated_by ? (int) $run->initiated_by : null,
            'initiated_at' => $run->created_at?->toIso8601String(),
            'calculation_version' => $run->calculation_version,
            'run_status' => $run->run_status,
            'period_start' => Carbon::parse((string) $run->period_start)->toDateString(),
            'period_end' => Carbon::parse((string) $run->period_end)->toDateString(),
            'input_snapshot' => $run->getAttribute('input_snapshot'),
            'policy_snapshot' => $run->getAttribute('policy_snapshot'),
            'input_versions' => $run->inputVersions->map(fn (PayrollCalculationRunInput $input): array => [
                'id' => (int) $input->getKey(),
                'source_input_id' => (int) $input->employee_contract_payroll_input_id,
                'segment_start' => Carbon::parse((string) $input->segment_start)->toDateString(),
                'segment_end' => Carbon::parse((string) $input->segment_end)->toDateString(),
                'input_snapshot' => $input->getAttribute('input_snapshot'),
            ])->all(),
            'result' => $run->result ? [
                'status' => $run->result->result_status,
                'snapshot_hash' => $run->result->snapshot_hash,
                'result_snapshot' => $run->result->getAttribute('result_snapshot'),
            ] : null,
        ])->all();

        $data = [
            'schema_version' => 'payroll-period-approval-v1',
            'employee_pay_slip' => [
                'id' => (int) $record->getKey(),
                'employee_id' => (int) $record->employee_id,
                'month' => Carbon::parse((string) $record->month)->toDateString(),
                'legacy_items' => $record->getAttribute('items'),
                'manual_amount' => (string) $record->manual_amount,
                'amount' => (string) $record->amount,
                'due_on' => $record->due_on ? Carbon::parse((string) $record->due_on)->toDateString() : null,
                'source_items' => $record->lineItems->map(fn (EmployeePaySlipItem $item): array => [
                    'id' => (int) $item->getKey(),
                    'item_type' => $item->item_type,
                    'source_type' => $item->source_type,
                    'source_id' => (int) $item->source_id,
                    'occurred_on' => Carbon::parse((string) $item->occurred_on)->toDateString(),
                    'description' => $item->description,
                    'quantity' => (string) $item->quantity,
                    'unit' => $item->unit,
                    'unit_rate' => (string) $item->unit_rate,
                    'amount' => (string) $item->amount,
                    'currency' => $item->currency,
                    'calculation_snapshot' => $item->getAttribute('calculation_snapshot'),
                ])->all(),
            ],
            'calculation_runs' => $runs,
        ];
        $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));

        return ['data' => $data, 'hash' => $hash, 'initiated_by' => $latestRun->initiated_by ? (int) $latestRun->initiated_by : null];
    }

    private function assertNoBlockingExceptions(EmployeePaySlip $paySlip): void
    {
        app(PayrollValidationService::class)->assertNoBlockingExceptionsForPeriod(
            (int) $paySlip->employee_id,
            Carbon::parse((string) $paySlip->month)->startOfMonth()->toDateString(),
            Carbon::parse((string) $paySlip->month)->endOfMonth()->toDateString(),
            (int) $paySlip->getKey(),
        );
    }

    private function event(
        EmployeePaySlip $paySlip,
        User $actor,
        string $type,
        ?string $from,
        ?string $to,
        ?string $note,
        ?string $hash,
        array $metadata = [],
        ?EmployeePaySlip $relatedPaySlip = null,
        ?EmployeePaySlipItem $relatedItem = null,
    ): void {
        PayrollPeriodLifecycleEvent::query()->create([
            'employee_pay_slip_id' => $paySlip->getKey(),
            'actor_id' => $actor->getKey(),
            'related_pay_slip_id' => $relatedPaySlip?->getKey(),
            'related_item_id' => $relatedItem?->getKey(),
            'event_type' => $type,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
            'snapshot_hash' => $hash,
            'metadata' => $metadata === [] ? null : $metadata,
            'created_at' => now(),
        ]);
    }

    private function authorize(User $actor, string $ability): void
    {
        if (Gate::forUser($actor)->allows($ability)) {
            return;
        }
        if (auth()->id() === $actor->getKey() && function_exists('hexa') && hexa()->can($ability)) {
            return;
        }

        throw new AuthorizationException('The required payroll period lifecycle permission is missing: '.$ability);
    }

    private function requiredNote(string $note, string $field): string
    {
        $note = trim($note);
        if ($note === '' || mb_strlen($note) > 2000) {
            throw ValidationException::withMessages([$field => 'A note of 1 to 2000 characters is required.']);
        }

        return $note;
    }
}
