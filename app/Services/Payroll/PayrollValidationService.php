<?php

namespace App\Services\Payroll;

use App\Models\PayrollCalculationRun;
use App\Models\PayrollException;
use App\Models\PayrollExceptionEvent;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PayrollValidationService
{
    /** @var array<string, array{severity: string, message: string, guidance: string}> */
    private const RULES = [
        'verified_payroll_input_missing' => ['severity' => 'blocking', 'message' => 'No effective-dated payroll input covers this period.', 'guidance' => 'Add and verify a complete payroll input for the employee and period.'],
        'payroll_input_not_verified' => ['severity' => 'blocking', 'message' => 'A selected payroll input has not been verified.', 'guidance' => 'Complete the input review before creating another calculation run.'],
        'payroll_input_incomplete' => ['severity' => 'blocking', 'message' => 'A selected payroll input is missing required calculation fields.', 'guidance' => 'Complete the required salary basis, amount, currency, and schedule fields, then verify the input.'],
        'payroll_input_effective_date_gap' => ['severity' => 'blocking', 'message' => 'Effective-dated payroll inputs leave part of the contract period uncovered.', 'guidance' => 'Add or correct effective-dated inputs so the active period is covered without gaps.'],
        'payroll_input_effective_date_overlap' => ['severity' => 'blocking', 'message' => 'Effective-dated payroll inputs overlap.', 'guidance' => 'Correct the effective dates so exactly one input applies on each active day.'],
        'payroll_input_outside_active_period' => ['severity' => 'blocking', 'message' => 'A selected payroll input falls outside the active contract period.', 'guidance' => 'Select an input whose effective dates overlap the contract period.'],
        'effective_policy_missing' => ['severity' => 'blocking', 'message' => 'No versioned payroll policy was selected.', 'guidance' => 'Select an explicitly configured payroll policy version effective for this period.'],
        'policy_not_effective_for_full_period' => ['severity' => 'blocking', 'message' => 'The selected payroll policy is not effective for the full period.', 'guidance' => 'Select a policy version whose effective dates cover the complete payroll period.'],
        'policy_configuration_incomplete' => ['severity' => 'blocking', 'message' => 'The selected policy does not configure this contract and its required inputs.', 'guidance' => 'Complete and review the policy configuration for this contract type before recalculating.'],
        'contract_type_separate_workflow' => ['severity' => 'blocking', 'message' => 'This worker arrangement uses a separate contractor invoice workflow.', 'guidance' => 'Use the approved contractor invoice lifecycle instead of employee payroll calculation.'],
        'contract_does_not_overlap_period' => ['severity' => 'blocking', 'message' => 'The selected contract is not active during this payroll period.', 'guidance' => 'Select the contract that was active during the requested period.'],
        'contract_period_proration_rule_missing' => ['severity' => 'blocking', 'message' => 'A partial contract period has no configured proration rule.', 'guidance' => 'Configure and approve an explicit proration rule before calculating this period.'],
        'period_input_not_approved' => ['severity' => 'blocking', 'message' => 'A payroll-period input has not been approved.', 'guidance' => 'Complete the input review workflow before calculating payroll.'],
        'period_input_policy_unapproved' => ['severity' => 'blocking', 'message' => 'The payroll-period input has no specialist-approved calculation rule.', 'guidance' => 'Keep the input informational until its calculation treatment is explicitly reviewed and configured.'],
        'period_input_source_value_missing' => ['severity' => 'blocking', 'message' => 'A payroll-period input has no usable source value.', 'guidance' => 'Provide a verified amount or quantity and its required unit.'],
        'period_input_source_link_missing' => ['severity' => 'blocking', 'message' => 'A payroll-period input is missing its source reference.', 'guidance' => 'Link the input to its originating record before calculation.'],
        'payslip_is_locked' => ['severity' => 'blocking', 'message' => 'The payslip is not open for calculation.', 'guidance' => 'Use a later open period and the documented correction workflow.'],
        'parameter_definition_invalid' => ['severity' => 'blocking', 'message' => 'A configured payroll rule is invalid.', 'guidance' => 'Review the versioned policy parameter definition and supported calculation type.'],
        'unsupported_calculation_method' => ['severity' => 'blocking', 'message' => 'A payroll rule uses an unsupported calculation method.', 'guidance' => 'Replace it with a registered calculation method supported by the engine.'],
        'calculation_input_missing' => ['severity' => 'blocking', 'message' => 'A configured calculation requires an input that is unavailable.', 'guidance' => 'Supply and verify the required source input; do not substitute an assumed value.'],
        'calculation_currency_mismatch' => ['severity' => 'blocking', 'message' => 'The calculation combines incompatible currency inputs.', 'guidance' => 'Use one explicit currency or provide a separately verified conversion record.'],
        'calculation_totals_inconsistent' => ['severity' => 'blocking', 'message' => 'The calculated gross, deductions, and net totals do not reconcile.', 'guidance' => 'Review the calculation lines and policy arithmetic before approving or closing the period.'],
    ];

    /** @return Collection<int, PayrollException> */
    public function recordRun(PayrollCalculationRun $run, array $resultSnapshot): Collection
    {
        $definitions = [];
        foreach ((array) $run->blocked_reasons as $code) {
            $definitions[$code] = self::RULES[$code] ?? [
                'severity' => PayrollException::SEVERITY_BLOCKING,
                'message' => 'Payroll validation found an unsupported or unresolved rule condition.',
                'guidance' => 'Review the selected source inputs and versioned policy; resolve the condition before approval.',
            ];
        }

        foreach ((array) ($resultSnapshot['validation_messages'] ?? []) as $validationMessage) {
            $code = is_array($validationMessage) ? (string) ($validationMessage['code'] ?? 'calculation_validation_failed') : 'calculation_validation_failed';
            $definitions[$code] = self::RULES[$code] ?? [
                'severity' => PayrollException::SEVERITY_BLOCKING,
                'message' => 'A configured payroll calculation rule did not pass validation.',
                'guidance' => 'Review the policy configuration and required source inputs. Sensitive input values are not included in this exception.',
            ];
        }

        if (($resultSnapshot['status'] ?? null) === 'calculated') {
            $gross = (string) ($resultSnapshot['gross_amount'] ?? '');
            $deductions = (string) ($resultSnapshot['deductions_amount'] ?? '');
            $net = (string) ($resultSnapshot['net_amount'] ?? '');
            if ($gross === '' || $deductions === '' || $net === '' || ! $this->totalsReconcile($gross, $deductions, $net)) {
                $definitions['calculation_totals_inconsistent'] = self::RULES['calculation_totals_inconsistent'];
            }
        }

        $duplicateExists = PayrollCalculationRun::query()
            ->where('employee_contract_id', $run->employee_contract_id)
            ->whereDate('period_start', $run->period_start)
            ->whereDate('period_end', $run->period_end)
            ->whereKeyNot($run->getKey())
            ->exists();
        if ($duplicateExists) {
            $definitions['duplicate_calculation_run'] = [
                'severity' => PayrollException::SEVERITY_WARNING,
                'message' => 'Another calculation snapshot already exists for this contract and period.',
                'guidance' => 'Confirm that this run is an intentional recalculation or correction and retain the earlier snapshot for comparison.',
            ];
        }

        return DB::transaction(function () use ($run, $definitions): Collection {
            $exceptions = collect();
            foreach ($definitions as $code => $definition) {
                $key = hash('sha256', implode('|', [(string) $run->getKey(), $code]));
                $exception = PayrollException::query()->firstOrCreate(
                    ['exception_key' => $key],
                    [
                        'payroll_calculation_run_id' => $run->getKey(),
                        'employee_pay_slip_id' => $run->employee_pay_slip_id,
                        'employee_contract_id' => $run->employee_contract_id,
                        'employee_id' => $run->employee_id,
                        'period_start' => $run->period_start,
                        'period_end' => $run->period_end,
                        'code' => $code,
                        'severity' => $definition['severity'],
                        'status' => PayrollException::STATUS_OPEN,
                        'message' => $definition['message'],
                        'resolution_guidance' => $definition['guidance'],
                        'affected_records' => [
                            'calculation_run_id' => (int) $run->getKey(),
                            'employee_id' => (int) $run->employee_id,
                            'employee_contract_id' => (int) $run->employee_contract_id,
                            'employee_pay_slip_id' => $run->employee_pay_slip_id ? (int) $run->employee_pay_slip_id : null,
                        ],
                        'detected_by' => $run->initiated_by,
                        'detected_at' => now(),
                    ],
                );
                if ($exception->wasRecentlyCreated) {
                    PayrollExceptionEvent::query()->create([
                        'payroll_exception_id' => $exception->getKey(),
                        'actor_id' => $run->initiated_by,
                        'event_type' => 'detected',
                        'note' => 'Exception detected by payroll validation.',
                        'metadata' => ['code' => $code, 'severity' => $definition['severity']],
                        'created_at' => now(),
                    ]);
                }
                $exceptions->push($exception);
            }

            return $exceptions;
        });
    }

    public function assertNoBlockingExceptionsForPeriod(int $employeeId, string $periodStart, string $periodEnd, ?int $paySlipId = null): void
    {
        $hasBlockingExceptions = PayrollException::query()
            ->where('severity', PayrollException::SEVERITY_BLOCKING)
            ->where('status', PayrollException::STATUS_OPEN)
            ->where(function ($query) use ($employeeId, $periodStart, $periodEnd, $paySlipId): void {
                $query->where(function ($periodQuery) use ($employeeId, $periodStart, $periodEnd): void {
                    $periodQuery->where('employee_id', $employeeId)
                        ->whereDate('period_start', $periodStart)
                        ->whereDate('period_end', $periodEnd);
                });
                if ($paySlipId !== null) {
                    $query->orWhere('employee_pay_slip_id', $paySlipId);
                }
            })
            ->exists();

        if ($hasBlockingExceptions) {
            throw ValidationException::withMessages(['status' => 'Resolve all blocking payroll exceptions before approving or closing this period.']);
        }
    }

    public function resolve(User $actor, PayrollException $exception, string $note): PayrollException
    {
        Gate::forUser($actor)->authorize('payroll_exception.resolve');
        $note = trim($note);
        if ($note === '' || mb_strlen($note) > 2000) {
            throw ValidationException::withMessages(['resolution_note' => 'Provide a resolution note of 1 to 2000 characters.']);
        }

        return DB::transaction(function () use ($actor, $exception, $note): PayrollException {
            $locked = PayrollException::query()->whereKey($exception->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== PayrollException::STATUS_OPEN) {
                throw ValidationException::withMessages(['status' => 'Only open payroll exceptions can be resolved.']);
            }
            $locked->forceFill([
                'status' => PayrollException::STATUS_RESOLVED,
                'resolved_by' => $actor->getKey(),
                'resolved_at' => now(),
                'resolution_note' => $note,
            ])->save();
            PayrollExceptionEvent::query()->create([
                'payroll_exception_id' => $locked->getKey(),
                'actor_id' => $actor->getKey(),
                'event_type' => 'resolved',
                'note' => $note,
                'metadata' => ['previous_status' => PayrollException::STATUS_OPEN],
                'created_at' => now(),
            ]);

            return $locked->fresh(['events']);
        });
    }

    private function totalsReconcile(string $gross, string $deductions, string $net): bool
    {
        if (! is_numeric($gross) || ! is_numeric($deductions) || ! is_numeric($net)) {
            return false;
        }
        if (function_exists('bcsub') && function_exists('bccomp')) {
            return bccomp(bcsub($gross, $deductions, 2), $net, 2) === 0;
        }

        return round((float) $gross - (float) $deductions, 2) === round((float) $net, 2);
    }
}
