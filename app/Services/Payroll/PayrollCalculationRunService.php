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
        return $this->persist($actor, $contract, $periodStart, $periodEnd, $idempotencyKey, $input, $policy, $paySlip, false, []);
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
        array $additionalInputs = [],
    ): PayrollCalculationRun {
        return $this->persist($actor, $contract, $periodStart, $periodEnd, $idempotencyKey, $input, $policy, $paySlip, true, $additionalInputs);
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
        array $additionalInputs,
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
        $providedInputs = array_merge($input ? [$input] : [], $additionalInputs);
        $requestedInputs = array_values(array_filter($providedInputs, fn (mixed $candidate): bool => $candidate instanceof EmployeeContractPayrollInput));
        if (count($requestedInputs) !== count($providedInputs)) {
            throw ValidationException::withMessages(['employee_contract_payroll_input_id' => 'Every selected payroll input must be a valid input record.']);
        }
        $requestedInputIds = array_map(fn (EmployeeContractPayrollInput $candidate): int => (int) $candidate->getKey(), $requestedInputs);
        if (count($requestedInputIds) !== count(array_unique($requestedInputIds))) {
            throw ValidationException::withMessages(['employee_contract_payroll_input_id' => 'Each effective-dated payroll input can be selected only once.']);
        }
        sort($requestedInputIds);
        foreach ($requestedInputs as $requestedInput) {
            if ((int) $requestedInput->employee_contract_id !== (int) $contract->getKey()) {
                throw ValidationException::withMessages(['employee_contract_payroll_input_id' => 'Every payroll input must belong to the selected contract.']);
            }
        }
        if ($paySlip && ((int) $paySlip->employee_id !== (int) $contract->employee_id || Carbon::parse((string) $paySlip->month)->format('Y-m') !== $start->format('Y-m'))) {
            throw ValidationException::withMessages(['employee_pay_slip_id' => 'The payslip must match the contract employee and payroll period.']);
        }

        return DB::transaction(function () use ($actor, $contract, $start, $end, $idempotencyKey, $requestedInputIds, $policy, $paySlip, $calculate): PayrollCalculationRun {
            $lockedContract = EmployeeContract::query()->whereKey($contract->getKey())->lockForUpdate()->firstOrFail();
            $lockedInputs = $requestedInputIds === []
                ? collect()
                : EmployeeContractPayrollInput::query()->whereIn('id', $requestedInputIds)->orderBy('effective_from')->orderBy('id')->lockForUpdate()->get();
            if ($lockedInputs->count() !== count($requestedInputIds)) {
                throw ValidationException::withMessages(['employee_contract_payroll_input_id' => 'A selected payroll input no longer exists.']);
            }
            foreach ($lockedInputs as $lockedInputRecord) {
                if ((int) $lockedInputRecord->employee_contract_id !== (int) $lockedContract->getKey()) {
                    throw ValidationException::withMessages(['employee_contract_payroll_input_id' => 'Every selected payroll input must belong to the selected contract.']);
                }
            }
            $lockedInput = $lockedInputs->first();
            $lockedPolicy = $policy ? PayrollPolicy::query()->whereKey($policy->getKey())->lockForUpdate()->firstOrFail() : null;
            $periodStart = $start->toDateString();
            $periodEnd = $end->toDateString();
            $blockedReasons = [];
            $policyConfigurationGaps = [];
            if (in_array(str_replace(['-', ' '], '_', strtolower((string) $lockedContract->contract_type)), ['contractor', 'pfa_contractor'], true)) {
                $blockedReasons[] = 'contract_type_separate_workflow';
            }

            $contractStart = Carbon::parse((string) $lockedContract->start_date)->toDateString();
            $contractEnd = Carbon::parse((string) $lockedContract->end_date)->toDateString();
            $hasContractOverlap = $contractStart <= $periodEnd && $contractEnd >= $periodStart;
            if (! $hasContractOverlap) {
                $blockedReasons[] = 'contract_does_not_overlap_period';
                $activeStart = $periodStart;
                $activeEnd = $periodEnd;
            } else {
                $activeStart = max($contractStart, $periodStart);
                $activeEnd = min($contractEnd, $periodEnd);
            }
            $periodDays = (int) $start->daysInMonth;
            $activeDays = (int) Carbon::parse($activeStart)->diffInDays(Carbon::parse($activeEnd)) + 1;
            $cursor = Carbon::parse($activeStart);
            $inputSegments = [];
            if ($lockedInputs->isEmpty()) {
                $blockedReasons[] = 'verified_payroll_input_missing';
            }
            foreach ($lockedInputs as $lockedInputRecord) {
                if (! $hasContractOverlap) {
                    $blockedReasons[] = 'payroll_input_outside_active_period';

                    continue;
                }
                if ($lockedInputRecord->input_status !== 'verified') {
                    $blockedReasons[] = 'payroll_input_not_verified';
                } elseif (! $lockedInputRecord->isCompleteForCalculation()) {
                    $blockedReasons[] = 'payroll_input_incomplete';
                }
                $inputStart = Carbon::parse((string) $lockedInputRecord->effective_from);
                $inputEnd = $lockedInputRecord->effective_until
                    ? Carbon::parse((string) $lockedInputRecord->effective_until)
                    : Carbon::parse($activeEnd);
                $segmentStart = $inputStart->greaterThan(Carbon::parse($activeStart)) ? $inputStart : Carbon::parse($activeStart);
                $segmentEnd = $inputEnd->lessThan(Carbon::parse($activeEnd)) ? $inputEnd : Carbon::parse($activeEnd);
                if ($segmentStart->greaterThan(Carbon::parse($activeEnd)) || $segmentEnd->lessThan(Carbon::parse($activeStart))) {
                    $blockedReasons[] = 'payroll_input_outside_active_period';

                    continue;
                }
                if ($segmentStart->gt($cursor)) {
                    $blockedReasons[] = 'payroll_input_effective_date_gap';
                } elseif ($segmentStart->lt($cursor)) {
                    $blockedReasons[] = 'payroll_input_effective_date_overlap';
                }
                $segmentStartDate = $segmentStart->toDateString();
                $segmentEndDate = $segmentEnd->toDateString();
                $segmentDays = (int) $segmentStart->diffInDays($segmentEnd) + 1;
                $inputSegments[] = $this->snapshotInput($lockedInputRecord, $segmentStartDate, $segmentEndDate, $segmentDays, $periodDays, $activeDays);
                if ($segmentEnd->gte($cursor)) {
                    $cursor = $segmentEnd->copy()->addDay();
                }
            }
            if ($hasContractOverlap && $cursor->lte(Carbon::parse($activeEnd))) {
                $blockedReasons[] = 'payroll_input_effective_date_gap';
            }
            if ($activeStart !== $periodStart || $activeEnd !== $periodEnd) {
                $prorationRule = $lockedPolicy?->getAttribute('proration_rule');
                $prorationMethod = is_array($prorationRule) ? ($prorationRule['method'] ?? null) : null;
                if ($prorationMethod !== 'calendar_days') {
                    $blockedReasons[] = 'contract_period_proration_rule_missing';
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
                $policyConfigurationGaps = $lockedPolicy->configurationGapsForContract((string) $lockedContract->contract_type);
                if ($policyConfigurationGaps !== []) {
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
                'contract_active_period' => ['start' => $activeStart, 'end' => $activeEnd, 'days' => $activeDays, 'period_days' => $periodDays],
                'selected_input_ids' => $requestedInputIds,
                'payroll_input' => $inputSegments[0] ?? null,
                'payroll_inputs' => $inputSegments,
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
                'contract_rules' => $lockedPolicy->contract_rules,
                'proration_rule' => $lockedPolicy->proration_rule,
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
                'input_ids' => $requestedInputIds,
                'policy_id' => $lockedPolicy?->getKey(),
                'pay_slip_id' => $paySlip?->getKey(),
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'input_snapshot' => $fingerprintInputSnapshot,
                'policy_snapshot' => $policySnapshot,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            $inputSnapshotHash = hash('sha256', json_encode($fingerprintInputSnapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            $policySnapshotHash = hash('sha256', json_encode($policySnapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

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

            foreach ($inputSegments as $segment) {
                $run->inputVersions()->create([
                    'employee_contract_payroll_input_id' => $segment['id'],
                    'segment_start' => $segment['segment_start'],
                    'segment_end' => $segment['segment_end'],
                    'segment_days' => $segment['segment_days'],
                    'period_days' => $segment['period_days'],
                    'input_snapshot' => $segment,
                ]);
            }

            $resultStatus = $blockedReasons !== [] ? 'blocked' : ($calculate ? 'calculated' : 'not_calculated');
            $calculated = $resultStatus === 'calculated' && is_array($calculationOutput);
            $resultSnapshot = [
                'schema_version' => 1,
                'calculation_performed' => $calculated,
                'calculation_version' => $calculationVersion,
                'status' => $resultStatus,
                'policy_reference' => $lockedPolicy ? ['policy_key' => $lockedPolicy->policy_key, 'version' => (int) $lockedPolicy->version] : null,
                'input_snapshot_hash' => $inputSnapshotHash,
                'policy_snapshot_hash' => $policySnapshotHash,
                'blocked_reasons' => array_values(array_unique($blockedReasons)),
                'validation_messages' => $calculationOutput['validation_messages'] ?? [],
                'unresolved_configuration_gaps' => $calculationOutput['unresolved_configuration_gaps'] ?? $policyConfigurationGaps,
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

    /** @return array<string, mixed> */
    private function snapshotInput(EmployeeContractPayrollInput $input, string $segmentStart, string $segmentEnd, int $segmentDays, int $periodDays, int $activeDays): array
    {
        return [
            'id' => (int) $input->getKey(),
            'effective_from' => Carbon::parse((string) $input->effective_from)->toDateString(),
            'effective_until' => $input->effective_until ? Carbon::parse((string) $input->effective_until)->toDateString() : null,
            'segment_start' => $segmentStart,
            'segment_end' => $segmentEnd,
            'segment_days' => $segmentDays,
            'period_days' => $periodDays,
            'contract_active_days' => $activeDays,
            'salary_basis' => $input->salary_basis,
            'base_amount' => $input->getRawOriginal('base_amount') === null ? null : (string) $input->getRawOriginal('base_amount'),
            'currency_code' => $input->currency_code,
            'scheduled_hours_per_week' => $input->getRawOriginal('scheduled_hours_per_week') === null ? null : (string) $input->getRawOriginal('scheduled_hours_per_week'),
            'work_schedule' => $input->work_schedule,
            'allowance_inputs' => $input->allowance_inputs,
            'tax_status_code' => $input->tax_status_code,
            'source_reference' => $input->source_reference,
            'input_status' => $input->input_status,
            'reviewed_by' => $input->reviewed_by,
            'reviewed_at' => $input->reviewed_at ? Carbon::parse((string) $input->reviewed_at)->toIso8601String() : null,
        ];
    }
}
