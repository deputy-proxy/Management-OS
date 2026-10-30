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
        'proration_rule_unsupported' => ['severity' => 'blocking', 'message' => 'The selected proration rule is not supported by the calculation engine.', 'guidance' => 'Use the explicitly supported calendar-day rule or keep the run blocked pending specialist configuration.'],
        'currency_code_invalid' => ['severity' => 'blocking', 'message' => 'A payroll input or policy contains an invalid currency code.', 'guidance' => 'Provide one explicit uppercase three-letter currency code and verify that all amounts use it.'],
        'contract_type_unsupported' => ['severity' => 'blocking', 'message' => 'No calculation route is configured for this contract type.', 'guidance' => 'Configure and review an explicit contract-specific route; do not apply another contract type default.'],
        'contract_rule_invalid' => ['severity' => 'blocking', 'message' => 'The contract-specific payroll route contains invalid configuration.', 'guidance' => 'Correct the route fields and explicitly list supported salary bases, parameters, and calculation order.'],
        'salary_basis_unsupported_for_contract' => ['severity' => 'blocking', 'message' => 'The selected salary basis is not supported by this contract route.', 'guidance' => 'Select a supported salary basis or have the contract route reviewed and versioned.'],
        'payroll_input_segment_invalid' => ['severity' => 'blocking', 'message' => 'An effective-dated payroll input segment is malformed.', 'guidance' => 'Correct the segment dates and source snapshot, then verify the input again.'],
        'input_segment_period_invalid' => ['severity' => 'blocking', 'message' => 'An input segment has invalid inclusive period-day values.', 'guidance' => 'Rebuild the segment from valid calendar dates and recalculate the day counts.'],
        'proration_rule_missing' => ['severity' => 'blocking', 'message' => 'A partial-period calculation has no explicit proration rule.', 'guidance' => 'Configure and approve the supported calendar-day proration rule before recalculating.'],
        'policy_parameters_invalid' => ['severity' => 'blocking', 'message' => 'Payroll policy parameters are not a structured configuration.', 'guidance' => 'Correct the policy parameter structure and activate a reviewed version.'],
        'required_parameters_invalid' => ['severity' => 'blocking', 'message' => 'The policy required-parameter list is invalid.', 'guidance' => 'Provide an explicit list of required parameter paths in the policy version.'],
        'required_parameter_path_invalid' => ['severity' => 'blocking', 'message' => 'A required policy parameter path is invalid.', 'guidance' => 'Replace invalid paths with non-empty, supported parameter paths.'],
        'rounding_configuration_invalid' => ['severity' => 'blocking', 'message' => 'The policy rounding configuration is invalid.', 'guidance' => 'Select a supported rounding mode and precision from zero through six decimal places.'],
        'calculation_order_missing' => ['severity' => 'blocking', 'message' => 'The policy has no calculation order.', 'guidance' => 'Define an explicit non-empty calculation order for the configured earning and deduction rules.'],
        'calculation_step_invalid' => ['severity' => 'blocking', 'message' => 'A policy calculation step is invalid.', 'guidance' => 'Use non-empty parameter paths that exist in the active policy version.'],
        'calculation_type_unsupported' => ['severity' => 'blocking', 'message' => 'A policy uses an unsupported calculation type.', 'guidance' => 'Use a registered calculation type supported by the payroll engine.'],
        'item_type_invalid' => ['severity' => 'blocking', 'message' => 'A policy line does not declare a supported earning or deduction type.', 'guidance' => 'Set the line type explicitly to earning or deduction in a reviewed policy version.'],
        'base_amount_invalid' => ['severity' => 'blocking', 'message' => 'A payroll input segment has an invalid base amount.', 'guidance' => 'Provide an explicit non-negative decimal amount and verify its currency and salary basis.'],
        'period_conversion_invalid' => ['severity' => 'blocking', 'message' => 'The policy applies an invalid salary-period conversion.', 'guidance' => 'Remove period divisors from monthly amounts and review the salary basis configuration.'],
        'period_conversion_missing' => ['severity' => 'blocking', 'message' => 'An annual salary has no explicit period divisor.', 'guidance' => 'Configure a positive, reviewed period divisor for the annual salary basis.'],
        'fixed_amount_invalid' => ['severity' => 'blocking', 'message' => 'A fixed payroll amount is invalid.', 'guidance' => 'Provide an explicit non-negative decimal amount in the policy configuration.'],
        'fixed_amount_basis_missing' => ['severity' => 'blocking', 'message' => 'A fixed payroll amount has no amount basis.', 'guidance' => 'Declare whether the fixed amount applies per period or per month.'],
        'allowance_reference_invalid' => ['severity' => 'blocking', 'message' => 'An allowance rule has an invalid source reference.', 'guidance' => 'Provide an explicit allowance code, amount basis, and structured source input list.'],
        'allowance_amount_invalid' => ['severity' => 'blocking', 'message' => 'An allowance input has an invalid amount.', 'guidance' => 'Correct the source allowance to a non-negative decimal amount and verify it.'],
        'percentage_rate_invalid' => ['severity' => 'blocking', 'message' => 'A percentage rule has an invalid rate.', 'guidance' => 'Provide an explicit non-negative decimal percentage in the reviewed policy version.'],
        'earning_line_missing' => ['severity' => 'blocking', 'message' => 'The policy does not produce a configured earning line.', 'guidance' => 'Configure at least one supported earning line before running payroll.'],
        'deductions_exceed_gross' => ['severity' => 'blocking', 'message' => 'Configured deductions exceed configured earnings.', 'guidance' => 'Review earning and deduction rules and source values; do not approve a negative net result.'],
        'proration_period_invalid' => ['severity' => 'blocking', 'message' => 'The proration period-day values are invalid.', 'guidance' => 'Rebuild the calculation period from valid inclusive calendar dates.'],
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
