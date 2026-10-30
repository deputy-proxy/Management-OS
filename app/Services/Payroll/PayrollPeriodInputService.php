<?php

namespace App\Services\Payroll;

use App\Models\PayrollPeriodInput;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;

class PayrollPeriodInputService
{
    public function submit(User $actor, PayrollPeriodInput $input): PayrollPeriodInput
    {
        $this->authorize($actor, 'payroll_period_input.submit');
        if (! in_array($input->status, ['draft', 'rejected'], true)) {
            throw ValidationException::withMessages(['status' => 'Only draft or rejected inputs can be submitted.']);
        }
        $input->status = 'submitted';
        $input->reviewed_by = null;
        $input->reviewed_at = null;
        $input->review_note = null;
        $input->save();

        return $input->refresh();
    }

    public function approve(User $actor, PayrollPeriodInput $input, ?string $note = null): PayrollPeriodInput
    {
        $this->authorize($actor, 'payroll_period_input.approve');

        return DB::transaction(function () use ($actor, $input, $note): PayrollPeriodInput {
            $locked = PayrollPeriodInput::query()->lockForUpdate()->findOrFail($input->getKey());
            if ($locked->status !== 'submitted') {
                throw ValidationException::withMessages(['status' => 'Only submitted inputs can be approved.']);
            }
            if ($locked->amount === null && $locked->quantity === null) {
                throw ValidationException::withMessages(['amount' => 'A source amount or quantity is required before approval.']);
            }
            if (! $locked->source_type || ! $locked->source_reference) {
                throw ValidationException::withMessages(['source_reference' => 'A source link is required before approval.']);
            }
            $locked->status = 'approved';
            $locked->reviewed_by = $actor->getKey();
            $locked->reviewed_at = now();
            $locked->review_note = $note;
            $locked->save();

            return $locked;
        });
    }

    public function reject(User $actor, PayrollPeriodInput $input, string $note): PayrollPeriodInput
    {
        $this->authorize($actor, 'payroll_period_input.approve');
        if (trim($note) === '') {
            throw ValidationException::withMessages(['review_note' => 'A rejection reason is required.']);
        }

        return DB::transaction(function () use ($actor, $input, $note): PayrollPeriodInput {
            $locked = PayrollPeriodInput::query()->lockForUpdate()->findOrFail($input->getKey());
            if ($locked->status !== 'submitted') {
                throw ValidationException::withMessages(['status' => 'Only submitted inputs can be rejected.']);
            }
            $locked->status = 'rejected';
            $locked->reviewed_by = $actor->getKey();
            $locked->reviewed_at = now();
            $locked->review_note = $note;
            $locked->save();

            return $locked;
        });
    }

    public function correct(User $actor, PayrollPeriodInput $original, array $changes): PayrollPeriodInput
    {
        $this->authorize($actor, 'payroll_period_input.create');
        if (! $original->exists || $original->status !== 'approved') {
            throw ValidationException::withMessages(['corrects_id' => 'Corrections must reference an approved source entry.']);
        }
        if (array_intersect(array_keys($changes), ['employee_id', 'employee_contract_id', 'corrects_id']) !== []) {
            throw ValidationException::withMessages(['corrects_id' => 'A correction must remain linked to the original employee and contract.']);
        }

        return PayrollPeriodInput::query()->create(array_merge($original->only([
            'employee_id', 'employee_contract_id', 'period_start', 'period_end', 'effective_from', 'effective_until',
            'category', 'entry_type', 'description', 'quantity', 'unit', 'amount', 'currency_code', 'source_type', 'source_reference', 'notes',
        ]), $changes, ['corrects_id' => $original->getKey(), 'status' => 'draft', 'recorded_by' => $actor->getKey(), 'reviewed_by' => null, 'reviewed_at' => null]));
    }

    private function authorize(User $actor, string $ability): void
    {
        if (Gate::forUser($actor)->allows($ability)) {
            return;
        }
        if (auth()->id() === $actor->getKey() && function_exists('hexa') && hexa()->can($ability)) {
            return;
        }
        throw new LogicException('The required payroll period input permission is missing: '.$ability);
    }
}
