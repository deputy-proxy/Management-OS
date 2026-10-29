<?php

namespace App\Services\Payroll;

use App\Models\EmployeeContract;
use App\Models\EmployeeContractPayrollInput;
use App\Models\EmployeePaySlip;
use App\Models\PayrollCalculationResult;
use App\Models\PayrollCalculationRun;
use App\Models\PayrollPolicy;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PayrollCalculationRunService
{
    public function record(
        User $actor,
        EmployeeContract $contract,
        string $periodStart,
        string $periodEnd,
        string $idempotencyKey,
        ?EmployeeContractPayrollInput $input = null,
        ?PayrollPolicy $policy = null,
        ?EmployeePaySlip $paySlip = null,
    ): PayrollCalculationRun {
        return $this->persist($actor, $contract, $periodStart, $periodEnd, $idempotencyKey, $input, $policy, $paySlip, false);
    }

    public function calculate(
        User $actor,
        EmployeeContract $contract,
        string $periodStart,
        string $periodEnd,
        string $idempotencyKey,
        ?EmployeeContractPayrollInput $input = null,
        ?PayrollPolicy $policy = null,
        ?EmployeePaySlip $paySlip = null,
    ): PayrollCalculationRun {
        return $this->persist($actor, $contract, $periodStart, $periodEnd, $idempotencyKey, $input, $policy, $paySlip, true);
    }

    private function persist(
        User $actor,
        EmployeeContract $contract,
        string $periodStart,
        string $periodEnd,
        string $idempotencyKey,
        ?EmployeeContractPayrollInput $input,
        ?PayrollPolicy $policy,
        ?EmployeePaySlip $paySlip,
        bool $calculate,
    ): PayrollCalculationRun {
        Gate::forUser($actor)->authorize('payroll_calculation_run.create');
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '' || mb_strlen($idempotencyKey) > 128) {
            throw ValidationException::withMessages(['idempotency_key' => 'Provide an idempotency key of 1 to 128 characters.']);
        }
        try {
            $start = Carbon::parse($periodStart)->startOfDay();
            $end = Carbon::parse($periodEnd)->startOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['period_start' => 'Provide valid payroll period dates.']);
        }
        if ($end->lt($start) || $start->day !== 1 || ! $end->isLastOfMonth() || $end->format('Y-m') !== $start->format('Y-m')) {
            throw ValidationException::withMessages(['period_start' => 'Payroll periods must be one complete calendar month.']);
        }
        if ((int) $contract->employee_id < 1) {
            throw ValidationException::withMessages(['employee_contract_id' => 'The contract must identify an employee.']);
        }
        if ($input && (int) $input->employee_contract_id !== (int) $contract->getKey()) {
            throw ValidationException::withMessages(['employee_contract_payroll_input_id' => 'The payroll input must belong to the selected contract.']);
        }
        if ($paySlip && ((int) $paySlip->employee_id !== (int) $contract->employee_id || Carbon::parse((string) $paySlip->month)->format('Y-m') !== $start->format('Y-m'))) {
            throw ValidationException::withMessages(['employee_pay_slip_id' => 'The payslip must match the contract employee and payroll period.']);
        }

        return DB::transaction(function () use ($actor, $contract, $start, $end, $idempotencyKey, $input, $policy, $paySlip, $calculate): PayrollCalculationRun {
            $lockedContract = EmployeeContract::query()->whereKey($contract->getKey())->lockForUpdate()->firstOrFail();
            $lockedInput = $input ? EmployeeContractPayrollInput::query()->whereKey($input->getKey())->lockForUpdate()->firstOrFail() : null;
            $lockedPolicy = $policy ? PayrollPolicy::query()->whereKey($policy->getKey())->lockForUpdate()->firstOrFail() : null;
            $periodStart = $start->toDateString();
            $periodEnd = $end->toDateString();
            $blockedReasons = [];

            $contractStart = Carbon::parse((string) $lockedContract->start_date)->toDateString();
            $contractEnd = Carbon::parse((string) $lockedContract->end_date)->toDateString();
            if ($contractStart > $periodStart || $contractEnd < $periodEnd) {
                $blockedReasons[] = 'contract_does_not_cover_full_period';
            }
            if (! $lockedInput) {
                $blockedReasons[] = 'verified_payroll_input_missing';
            } else {
                if ($lockedInput->input_status !== 'verified') {
                    $blockedReasons[] = 'payroll_input_not_verified';
                } elseif (! $lockedInput->isCompleteForCalculation()) {
                    $blockedReasons[] = 'payroll_input_incomplete';
                }
                if (Carbon::parse((string) $lockedInput->effective_from)->toDateString() > $periodStart
                    || ($lockedInput->effective_until && Carbon::parse((string) $lockedInput->effective_until)->toDateString() < $periodEnd)) {
                    $blockedReasons[] = 'payroll_input_does_not_cover_full_period';
                }
            }
            if (! $lockedPolicy) {
                $blockedReasons[] = 'effective_policy_missing';
            } else {
                $policyStart = Carbon::parse((string) $lockedPolicy->effective_from)->toDateString();
                $policyEnd = $lockedPolicy->effective_until ? Carbon::parse((string) $lockedPolicy->effective_until)->toDateString() : null;
                if (! in_array($lockedPolicy->status, [PayrollPolicy::STATUS_ACTIVE, PayrollPolicy::STATUS_RETIRED], true)
                    || $policyStart > $periodStart || ($policyEnd !== null && $policyEnd < $periodEnd)) {
                    $blockedReasons[] = 'policy_not_effective_for_full_period';
                }
                if ($lockedPolicy->configurationGaps() !== []) {
                    $blockedReasons[] = 'policy_configuration_incomplete';
                }
            }
            if ($paySlip && $paySlip->status !== 'Open') {
                $blockedReasons[] = 'payslip_is_locked';
            }

            $inputSnapshot = [
                'schema_version' => 1,
                'captured_at' => now()->toIso8601String(),
                'employee_id' => (int) $lockedContract->employee_id,
                'contract' => [
                    'id' => (int) $lockedContract->getKey(),
                    'start_date' => $contractStart,
                    'end_date' => $contractEnd,
                    'contract_type' => $lockedContract->contract_type,
                    'legacy_base_salary' => $lockedContract->getRawOriginal('base_salary') === null ? null : (string) $lockedContract->getRawOriginal('base_salary'),
                    'legacy_hourly_rate' => $lockedContract->getRawOriginal('hourly_rate') === null ? null : (string) $lockedContract->getRawOriginal('hourly_rate'),
                    'legacy_values_are_calculation_inputs' => false,
                ],
                'payroll_input' => $lockedInput ? [
                    'id' => (int) $lockedInput->getKey(),
                    'effective_from' => Carbon::parse((string) $lockedInput->effective_from)->toDateString(),
                    'effective_until' => $lockedInput->effective_until ? Carbon::parse((string) $lockedInput->effective_until)->toDateString() : null,
                    'salary_basis' => $lockedInput->salary_basis,
                    'base_amount' => $lockedInput->getRawOriginal('base_amount') === null ? null : (string) $lockedInput->getRawOriginal('base_amount'),
                    'currency_code' => $lockedInput->currency_code,
                    'scheduled_hours_per_week' => $lockedInput->getRawOriginal('scheduled_hours_per_week') === null ? null : (string) $lockedInput->getRawOriginal('scheduled_hours_per_week'),
                    'work_schedule' => $lockedInput->work_schedule,
                    'allowance_inputs' => $lockedInput->allowance_inputs,
                    'tax_status_code' => $lockedInput->tax_status_code,
                    'source_reference' => $lockedInput->source_reference,
                    'input_status' => $lockedInput->input_status,
                    'reviewed_by' => $lockedInput->reviewed_by,
                    'reviewed_at' => $lockedInput->reviewed_at ? Carbon::parse((string) $lockedInput->reviewed_at)->toIso8601String() : null,
                ] : null,
            ];
            $policySnapshot = $lockedPolicy ? [
                'id' => (int) $lockedPolicy->getKey(),
                'policy_key' => $lockedPolicy->policy_key,
                'version' => (int) $lockedPolicy->version,
                'status' => $lockedPolicy->status,
                'effective_from' => Carbon::parse((string) $lockedPolicy->effective_from)->toDateString(),
                'effective_until' => $lockedPolicy->effective_until ? Carbon::parse((string) $lockedPolicy->effective_until)->toDateString() : null,
                'parameters' => $lockedPolicy->parameters,
                'required_parameters' => $lockedPolicy->required_parameters,
                'calculation_order' => $lockedPolicy->calculation_order,
                'rounding_mode' => $lockedPolicy->rounding_mode,
                'rounding_precision' => $lockedPolicy->rounding_precision,
            ] : null;
            $fingerprintInputSnapshot = $inputSnapshot;
            unset($fingerprintInputSnapshot['captured_at']);
            $calculationVersion = $calculate ? PayrollCalculationEngine::VERSION : 'schema-only-v1';
            $calculationOutput = null;
            if ($calculate && $blockedReasons === []) {
                $calculationOutput = app(PayrollCalculationEngine::class)->calculate($inputSnapshot, $policySnapshot ?? []);
                if ($calculationOutput['status'] !== 'calculated') {
                    $blockedReasons = array_merge($blockedReasons, $calculationOutput['blocked_reasons']);
                }
            }
            $fingerprint = hash('sha256', json_encode([
                'calculation_requested' => $calculate,
                'calculation_version' => $calculationVersion,
                'employee_id' => (int) $lockedContract->employee_id,
                'contract_id' => (int) $lockedContract->getKey(),
                'input_id' => $lockedInput?->getKey(),
                'policy_id' => $lockedPolicy?->getKey(),
                'pay_slip_id' => $paySlip?->getKey(),
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'input_snapshot' => $fingerprintInputSnapshot,
                'policy_snapshot' => $policySnapshot,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

            $existing = PayrollCalculationRun::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if (! hash_equals((string) $existing->request_fingerprint, $fingerprint)) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This key already identifies a different calculation request. Use a new key for changed inputs.']);
                }

                return $existing->load('result.items');
            }

            $run = PayrollCalculationRun::query()->create([
                'employee_id' => $lockedContract->employee_id,
                'employee_contract_id' => $lockedContract->getKey(),
                'employee_contract_payroll_input_id' => $lockedInput?->getKey(),
                'payroll_policy_id' => $lockedPolicy?->getKey(),
                'employee_pay_slip_id' => $paySlip?->getKey(),
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'idempotency_key' => $idempotencyKey,
                'request_fingerprint' => $fingerprint,
                'run_status' => $blockedReasons !== [] ? PayrollCalculationRun::STATUS_BLOCKED : ($calculate ? PayrollCalculationRun::STATUS_CALCULATED : PayrollCalculationRun::STATUS_READY),
                'blocked_reasons' => array_values(array_unique($blockedReasons)),
                'input_snapshot' => $fingerprintInputSnapshot,
                'policy_snapshot' => $policySnapshot,
                'calculation_version' => $calculationVersion,
                'initiated_by' => $actor->getKey(),
            ]);

            $resultStatus = $blockedReasons !== [] ? 'blocked' : ($calculate ? 'calculated' : 'not_calculated');
            $calculated = $resultStatus === 'calculated' && is_array($calculationOutput);
            $resultSnapshot = [
                'schema_version' => 1,
                'calculation_performed' => $calculated,
                'calculation_version' => $calculationVersion,
                'status' => $resultStatus,
                'policy_reference' => $lockedPolicy ? ['policy_key' => $lockedPolicy->policy_key, 'version' => (int) $lockedPolicy->version] : null,
                'blocked_reasons' => array_values(array_unique($blockedReasons)),
                'validation_messages' => $calculationOutput['validation_messages'] ?? [],
                'unresolved_configuration_gaps' => $calculationOutput['unresolved_configuration_gaps'] ?? ($lockedPolicy?->configurationGaps() ?? []),
                'line_items' => $calculated ? $calculationOutput['line_items'] : [],
                'gross_amount' => $calculated ? $calculationOutput['gross_amount'] : null,
                'deductions_amount' => $calculated ? $calculationOutput['deductions_amount'] : null,
                'net_amount' => $calculated ? $calculationOutput['net_amount'] : null,
                'currency_code' => $calculated ? $calculationOutput['currency_code'] : null,
                'rounding_mode' => $calculated ? $calculationOutput['rounding_mode'] : $lockedPolicy?->rounding_mode,
                'rounding_precision' => $calculated ? $calculationOutput['rounding_precision'] : $lockedPolicy?->rounding_precision,
                'note' => $calculated
                    ? 'Calculated using the explicitly selected policy version and deterministic calculation engine.'
                    : ($calculate ? 'Calculation blocked. No monetary result was produced.' : 'Inputs and policy were snapshotted only. No calculation was performed.'),
            ];
            $result = PayrollCalculationResult::query()->create([
                'payroll_calculation_run_id' => $run->getKey(),
                'result_status' => $resultStatus,
                'currency_code' => $calculated ? $calculationOutput['currency_code'] : null,
                'gross_amount' => $calculated ? $calculationOutput['gross_amount'] : null,
                'deductions_amount' => $calculated ? $calculationOutput['deductions_amount'] : null,
                'net_amount' => $calculated ? $calculationOutput['net_amount'] : null,
                'result_snapshot' => $resultSnapshot,
                'snapshot_hash' => hash('sha256', json_encode($resultSnapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
                'snapshotted_at' => now(),
            ]);
            foreach (($calculated ? $calculationOutput['line_items'] : []) as $line) {
                $result->items()->create([
                    'line_number' => $line['line_number'],
                    'item_code' => $line['item_code'],
                    'item_type' => $line['item_type'],
                    'label' => $line['label'],
                    'calculation_method' => $line['calculation_method'],
                    'source_parameter' => $line['source_parameter'],
                    'base_item_code' => $line['base_item_code'],
                    'input_value' => $line['input_value'],
                    'rate_percent' => $line['rate_percent'],
                    'amount' => $line['amount'],
                    'currency_code' => $line['currency_code'],
                    'rounding_mode' => $line['rounding_mode'],
                    'rounding_precision' => $line['rounding_precision'],
                    'calculation_snapshot' => $line['calculation_snapshot'],
                ]);
            }

            return $run->load('result.items');
        });
    }
}
