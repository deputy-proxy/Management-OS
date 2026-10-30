<?php

namespace Tests\Feature;

use App\Models\EmployeeContract;
use App\Models\EmployeeContractPayrollInput;
use App\Models\EmployeePaySlip;
use App\Models\PayrollCalculationRun;
use App\Models\PayrollException;
use App\Models\PayrollPolicy;
use App\Models\User;
use App\Services\Payroll\PayrollCalculationRunService;
use App\Services\Payroll\PayrollValidationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class PayrollCalculationRunPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::define('payroll_calculation_run.create', static fn (): bool => true);
        Gate::define('payroll_exception.resolve', static fn (): bool => true);
    }

    public function test_ready_run_persists_versioned_policy_and_explicit_input_snapshots_without_calculating_payroll(): void
    {
        [$actor, $contract, $input, $policy, $paySlip] = $this->fixture();

        $run = app(PayrollCalculationRunService::class)->record($actor, $contract, '2026-01-01', '2026-01-31', 'synthetic-run-001', $input, $policy, $paySlip);

        $this->assertSame(PayrollCalculationRun::STATUS_READY, $run->run_status);
        $this->assertSame([], $run->blocked_reasons);
        $this->assertSame($input->id, $run->input_snapshot['payroll_input']['id']);
        $this->assertSame('4000', $run->input_snapshot['payroll_input']['base_amount']);
        $this->assertSame('verified', $run->input_snapshot['payroll_input']['input_status']);
        $this->assertSame('synthetic_policy', $run->policy_snapshot['policy_key']);
        $this->assertSame(1, $run->policy_snapshot['version']);
        $this->assertFalse($run->input_snapshot['contract']['legacy_values_are_calculation_inputs']);
        $this->assertSame('not_calculated', $run->result->result_status);
        $this->assertNull($run->result->gross_amount);
        $this->assertNull($run->result->deductions_amount);
        $this->assertNull($run->result->net_amount);
        $this->assertFalse($run->result->result_snapshot['calculation_performed']);
        $this->assertSame(64, strlen($run->result->snapshot_hash));
        $this->assertSame($run->id, $paySlip->fresh()->calculationRuns()->firstOrFail()->id);
    }

    public function test_calculation_persists_deterministic_totals_and_immutable_line_items(): void
    {
        [$actor, $contract, $input, $policy, $paySlip] = $this->fixture(calculablePolicy: true);
        $legacyPayslipAmount = (string) $paySlip->amount;
        $service = app(PayrollCalculationRunService::class);
        $run = $service->calculate($actor, $contract, '2026-01-01', '2026-01-31', 'synthetic-calculation-001', $input, $policy, $paySlip);

        $this->assertSame(PayrollCalculationRun::STATUS_CALCULATED, $run->run_status);
        $this->assertSame('calculated', $run->result->result_status);
        $this->assertSame('4500.00', $run->result->result_snapshot['gross_amount']);
        $this->assertSame('200.00', $run->result->result_snapshot['deductions_amount']);
        $this->assertSame('4300.00', $run->result->result_snapshot['net_amount']);
        $this->assertSame('payroll-decimal-v1', $run->calculation_version);
        $this->assertSame(hash('sha256', json_encode($run->input_snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)), $run->result->result_snapshot['input_snapshot_hash']);
        $this->assertSame(hash('sha256', json_encode($run->policy_snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)), $run->result->result_snapshot['policy_snapshot_hash']);
        $this->assertSame($legacyPayslipAmount, (string) $paySlip->fresh()->amount, 'Calculation snapshots must not overwrite legacy payslip totals.');
        $this->assertCount(4, $run->result->items);
        $this->assertSame('bonus', $run->result->items[2]->item_code);
        $this->assertSame('percentage_of', $run->result->items[2]->calculation_method);
        try {
            $run->result->items[0]->forceFill(['amount' => '1.00'])->save();
            $this->fail('Calculated line items must be immutable.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('immutable', $exception->getMessage());
        }
        $this->assertSame('synthetic_policy', $run->result->result_snapshot['policy_reference']['policy_key']);
        $this->assertSame(1, $run->result->result_snapshot['policy_reference']['version']);
        $this->assertSame($run->id, $service->calculate($actor, $contract, '2026-01-01', '2026-01-31', 'synthetic-calculation-001', $input, $policy, $paySlip)->id);

        $newRun = $service->calculate($actor, $contract, '2026-01-01', '2026-01-31', 'synthetic-calculation-002', $input, $policy, $paySlip);
        $this->assertNotSame($run->id, $newRun->id);
        $this->assertSame($run->result->snapshot_hash, $newRun->result->snapshot_hash);
        $this->assertSame(2, PayrollCalculationRun::query()->where('employee_pay_slip_id', $paySlip->id)->count());
    }

    public function test_effective_policy_can_supply_a_contract_specific_formula_path(): void
    {
        [$actor, $contract, $input, , $paySlip] = $this->fixture();
        $routeParameters = [
            'contract_base' => ['type' => 'input_amount', 'input_field' => 'base_amount', 'expected_salary_basis' => 'monthly', 'item_type' => 'earning', 'label' => 'Route-specific base'],
            'route_allowance' => ['type' => 'input_allowance', 'allowance_code' => 'synthetic_allowance', 'amount_basis' => 'monthly_amount', 'item_type' => 'earning', 'label' => 'Route-specific allowance'],
        ];
        $policy = PayrollPolicy::factory()->create([
            'policy_key' => 'synthetic_route_only', 'version' => 1, 'status' => 'active',
            'effective_from' => '2026-01-01', 'effective_until' => '2026-12-31',
            'parameters' => [], 'required_parameters' => [], 'calculation_order' => [],
            'contract_rules' => ['full_time' => [
                'supported_salary_bases' => ['monthly'], 'parameters' => $routeParameters,
                'required_parameters' => array_keys($routeParameters), 'calculation_order' => array_keys($routeParameters),
            ]],
            'proration_rule' => null, 'rounding_mode' => 'half_up', 'rounding_precision' => 2,
        ]);

        $run = app(PayrollCalculationRunService::class)->calculate($actor, $contract, '2026-01-01', '2026-01-31', 'synthetic-route-only-001', $input, $policy, $paySlip);
        $this->assertSame('calculated', $run->result->result_status);
        $this->assertSame('4100.00', $run->result->result_snapshot['gross_amount']);
        $this->assertCount(2, $run->result->items);
        $this->assertSame('contract_base', $run->result->items[0]->item_code);
    }

    public function test_calculation_validation_errors_are_persisted_without_partial_lines_or_totals(): void
    {
        [$actor, $contract, $input, $policy, $paySlip] = $this->fixture();
        $run = app(PayrollCalculationRunService::class)->calculate($actor, $contract, '2026-01-01', '2026-01-31', 'synthetic-invalid-rule-001', $input, $policy, $paySlip);

        $this->assertSame(PayrollCalculationRun::STATUS_BLOCKED, $run->run_status);
        $this->assertSame('blocked', $run->result->result_status);
        $this->assertNull($run->result->net_amount);
        $this->assertSame([], $run->result->items->all());
        $this->assertContains('parameter_definition_invalid', array_column($run->result->result_snapshot['validation_messages'], 'code'));
        $this->assertDatabaseHas('payroll_exceptions', ['payroll_calculation_run_id' => $run->id, 'code' => 'parameter_definition_invalid', 'severity' => 'blocking']);
    }

    public function test_missing_policy_or_unverified_inputs_create_explicit_blocked_snapshot_without_inventing_values(): void
    {
        [$actor, $contract, $input] = $this->fixture(inputStatus: 'unverified');

        $run = app(PayrollCalculationRunService::class)->record($actor, $contract, '2026-01-01', '2026-01-31', 'synthetic-blocked-001', $input);

        $this->assertSame(PayrollCalculationRun::STATUS_BLOCKED, $run->run_status);
        $this->assertContains('payroll_input_not_verified', $run->blocked_reasons);
        $this->assertContains('effective_policy_missing', $run->blocked_reasons);
        $this->assertNull($run->policy_snapshot);
        $this->assertNull($run->result->currency_code);
        $this->assertNull($run->result->net_amount);
        $this->assertSame('blocked', $run->result->result_status);
        $this->assertDatabaseHas('payroll_exceptions', ['payroll_calculation_run_id' => $run->id, 'code' => 'payroll_input_not_verified']);
        $this->assertDatabaseHas('payroll_exceptions', ['payroll_calculation_run_id' => $run->id, 'code' => 'effective_policy_missing']);
    }

    public function test_verified_but_incomplete_inputs_are_blocked_instead_of_filling_unknown_values(): void
    {
        [$actor, $contract, $input, $policy] = $this->fixture(incomplete: true);

        $run = app(PayrollCalculationRunService::class)->record($actor, $contract, '2026-01-01', '2026-01-31', 'synthetic-incomplete-001', $input, $policy);

        $this->assertSame(PayrollCalculationRun::STATUS_BLOCKED, $run->run_status);
        $this->assertContains('payroll_input_incomplete', $run->blocked_reasons);
        $this->assertDatabaseHas('payroll_exceptions', ['payroll_calculation_run_id' => $run->id, 'code' => 'payroll_input_incomplete']);
        $this->assertNull($run->input_snapshot['payroll_input']['salary_basis']);
        $this->assertNull($run->input_snapshot['payroll_input']['currency_code']);
        $this->assertNull($run->input_snapshot['payroll_input']['scheduled_hours_per_week']);
    }

    public function test_input_and_policy_changes_do_not_rewrite_an_existing_snapshot(): void
    {
        [$actor, $contract, $input, $policy] = $this->fixture();
        $run = app(PayrollCalculationRunService::class)->record($actor, $contract, '2026-01-01', '2026-01-31', 'synthetic-history-001', $input, $policy);
        $savedSnapshot = $run->input_snapshot;
        $contract->update(['base_salary' => 9999]);

        $this->assertSame($savedSnapshot, $run->fresh()->input_snapshot);
        $this->assertSame('4000', $run->fresh()->input_snapshot['contract']['legacy_base_salary']);
        $this->assertSame(1, $run->fresh()->policy_snapshot['version']);
    }

    public function test_idempotency_returns_the_original_run_and_rejects_key_reuse_for_different_inputs(): void
    {
        [$actor, $contract, $input, $policy] = $this->fixture();
        $service = app(PayrollCalculationRunService::class);
        $first = $service->record($actor, $contract, '2026-01-01', '2026-01-31', 'synthetic-idempotent-001', $input, $policy);
        $second = $service->record($actor, $contract, '2026-01-01', '2026-01-31', 'synthetic-idempotent-001', $input, $policy);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, PayrollCalculationRun::query()->where('idempotency_key', 'synthetic-idempotent-001')->count());

        try {
            $service->record($actor, $contract, '2026-02-01', '2026-02-28', 'synthetic-idempotent-001', $input, $policy);
            $this->fail('An idempotency key cannot identify a different period.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('idempotency_key', $exception->errors());
        }
    }

    public function test_period_and_input_contract_must_match_and_period_must_be_one_calendar_month(): void
    {
        [$actor, $contract, $input, $policy] = $this->fixture();
        $otherContract = EmployeeContract::factory()->create(['employee_id' => $contract->employee_id, 'start_date' => '2026-01-01', 'end_date' => '2027-12-31']);

        try {
            app(PayrollCalculationRunService::class)->record($actor, $otherContract, '2026-01-01', '2026-01-31', 'synthetic-mismatch-001', $input, $policy);
            $this->fail('Input cannot be reused against a different contract.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('employee_contract_payroll_input_id', $exception->errors());
        }

        $this->expectException(ValidationException::class);
        app(PayrollCalculationRunService::class)->record($actor, $contract, '2026-01-01', '2026-02-28', 'synthetic-period-001', $input, $policy);
    }

    public function test_runs_and_result_snapshots_are_immutable_and_retained(): void
    {
        [$actor, $contract, $input, $policy] = $this->fixture();
        $run = app(PayrollCalculationRunService::class)->record($actor, $contract, '2026-01-01', '2026-01-31', 'synthetic-immutable-001', $input, $policy);

        try {
            $run->forceFill(['run_status' => 'calculated'])->save();
            $this->fail('Calculation runs must not be mutated after recording.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('immutable', $exception->getMessage());
        }

        try {
            $run->result->forceFill(['net_amount' => 1000])->save();
            $this->fail('Result snapshots must not be mutated.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('immutable', $exception->getMessage());
        }

        try {
            $run->result->delete();
            $this->fail('Result snapshots must not be deleted.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('cannot be deleted', $exception->getMessage());
        }
        $this->assertDatabaseHas('payroll_calculation_results', ['payroll_calculation_run_id' => $run->id, 'snapshot_hash' => $run->result->snapshot_hash]);
    }

    public function test_result_snapshot_migration_refuses_to_drop_recorded_history(): void
    {
        [$actor, $contract] = $this->fixture();
        $run = app(PayrollCalculationRunService::class)->record($actor, $contract, '2026-01-01', '2026-01-31', 'synthetic-rollback-001');
        $migration = require base_path('database/migrations/2026_10_29_020000_create_payroll_calculation_runs_and_results.php');

        $this->expectException(\RuntimeException::class);
        $migration->down();
        $this->assertDatabaseHas('payroll_calculation_runs', ['id' => $run->id]);
    }

    public function test_mid_period_input_changes_are_prorated_and_correction_runs_keep_prior_snapshots(): void
    {
        [$actor, $contract, $firstInput, $policy, $paySlip] = $this->fixture(calculablePolicy: true, prorate: true);
        $firstInput->update(['effective_until' => '2026-01-15']);
        $secondInput = $this->makeVerifiedInput($actor, $contract, '2026-01-16', '2026-12-31', '6000.00', '200.00');
        $service = app(PayrollCalculationRunService::class);
        $run = $service->calculate($actor, $contract, '2026-01-01', '2026-01-31', 'synthetic-midperiod-001', $firstInput, $policy, $paySlip, [$secondInput]);

        $this->assertSame('calculated', $run->result->result_status);
        $this->assertSame('5687.10', $run->result->result_snapshot['gross_amount']);
        $this->assertSame('251.61', $run->result->result_snapshot['deductions_amount']);
        $this->assertSame('5435.49', $run->result->result_snapshot['net_amount']);
        $this->assertSame([15, 16], $run->inputVersions->pluck('segment_days')->all());
        $this->assertSame(['2026-01-01', '2026-01-16'], $run->inputVersions->map(fn ($version): string => $version->segment_start->toDateString())->all());
        try {
            $run->inputVersions[0]->forceFill(['segment_days' => 1])->save();
            $this->fail('Run-to-input version links must be immutable.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('immutable', $exception->getMessage());
        }
        $this->assertSame('5032.260000', $run->result->items[0]->amount);
        $this->assertSame('151.610000', $run->result->items[1]->amount);

        $correctedParameters = $policy->parameters;
        $correctedParameters['bonus']['rate_percent'] = '12';
        $correctedPolicy = PayrollPolicy::factory()->create([
            'policy_key' => 'synthetic_policy_correction', 'version' => 1, 'status' => 'active',
            'effective_from' => '2026-01-01', 'effective_until' => '2026-12-31',
            'parameters' => $correctedParameters, 'required_parameters' => $policy->required_parameters,
            'calculation_order' => $policy->calculation_order, 'contract_rules' => $policy->contract_rules,
            'proration_rule' => $policy->proration_rule, 'rounding_mode' => $policy->rounding_mode,
            'rounding_precision' => $policy->rounding_precision,
        ]);
        $correctedRun = $service->calculate($actor, $contract, '2026-01-01', '2026-01-31', 'synthetic-midperiod-correction-001', $firstInput, $correctedPolicy, $paySlip, [$secondInput]);
        $this->assertNotSame($run->id, $correctedRun->id);
        $this->assertNotSame($run->result->snapshot_hash, $correctedRun->result->snapshot_hash);
        $this->assertSame('5687.10', $run->result->fresh()->result_snapshot['gross_amount']);
        $this->assertSame('synthetic_policy', $run->result->result_snapshot['policy_reference']['policy_key']);
    }

    public function test_partial_contract_period_requires_explicit_proration_and_then_calculates(): void
    {
        [$actor, $contract, $input, $policy, $paySlip] = $this->fixture(calculablePolicy: true, contractStart: '2026-01-15');
        $service = app(PayrollCalculationRunService::class);
        $blocked = $service->calculate($actor, $contract, '2026-01-01', '2026-01-31', 'synthetic-contract-proration-blocked', $input, $policy, $paySlip);
        $this->assertSame(PayrollCalculationRun::STATUS_BLOCKED, $blocked->run_status);
        $this->assertContains('contract_period_proration_rule_missing', $blocked->blocked_reasons);
        $this->assertDatabaseHas('payroll_exceptions', ['payroll_calculation_run_id' => $blocked->id, 'code' => 'contract_period_proration_rule_missing']);

        $configuredPolicy = PayrollPolicy::factory()->create([
            'policy_key' => 'synthetic_contract_proration', 'version' => 1, 'status' => 'active',
            'effective_from' => '2026-01-01', 'effective_until' => '2026-12-31',
            'parameters' => $policy->parameters, 'required_parameters' => $policy->required_parameters,
            'calculation_order' => $policy->calculation_order, 'contract_rules' => $policy->contract_rules,
            'proration_rule' => ['method' => 'calendar_days'], 'rounding_mode' => $policy->rounding_mode,
            'rounding_precision' => $policy->rounding_precision,
        ]);
        $calculated = $service->calculate($actor, $contract, '2026-01-01', '2026-01-31', 'synthetic-contract-proration-calculated', $input, $configuredPolicy, $paySlip);
        $this->assertSame('calculated', $calculated->result->result_status);
        $this->assertSame('2193.550000', $calculated->result->items[0]->amount);
        $this->assertSame(17, $calculated->inputVersions->first()->segment_days);
    }

    public function test_selected_effective_input_gaps_block_calculation(): void
    {
        [$actor, $contract, $firstInput, $policy, $paySlip] = $this->fixture(calculablePolicy: true, prorate: true);
        $firstInput->update(['effective_until' => '2026-01-10']);
        $secondInput = $this->makeVerifiedInput($actor, $contract, '2026-01-16', '2026-12-31', '6000.00', '200.00');
        $run = app(PayrollCalculationRunService::class)->calculate($actor, $contract, '2026-01-01', '2026-01-31', 'synthetic-midperiod-gap-001', $firstInput, $policy, $paySlip, [$secondInput]);

        $this->assertSame(PayrollCalculationRun::STATUS_BLOCKED, $run->run_status);
        $this->assertContains('payroll_input_effective_date_gap', $run->blocked_reasons);
        $this->assertDatabaseHas('payroll_exceptions', ['payroll_calculation_run_id' => $run->id, 'code' => 'payroll_input_effective_date_gap']);
        $this->assertNull($run->result->net_amount);
        $this->assertCount(2, $run->inputVersions);
    }

    public function test_engine_schema_migration_refuses_to_drop_explicit_base_amounts(): void
    {
        [$actor, $contract, $input] = $this->fixture();
        $migration = require base_path('database/migrations/2026_10_29_030000_add_payroll_calculation_engine_fields.php');

        $this->expectException(\RuntimeException::class);
        $migration->down();
        $this->assertDatabaseHas('employee_contract_payroll_inputs', ['id' => $input->id]);
    }

    public function test_contract_route_migration_refuses_to_drop_immutable_run_input_links(): void
    {
        [$actor, $contract, $input, $policy] = $this->fixture();
        $run = app(PayrollCalculationRunService::class)->record($actor, $contract, '2026-01-01', '2026-01-31', 'synthetic-route-rollback-001', $input, $policy);
        $migration = require base_path('database/migrations/2026_10_29_040000_add_contract_routes_and_run_inputs.php');

        $this->assertDatabaseHas('payroll_calculation_run_inputs', ['payroll_calculation_run_id' => $run->id, 'employee_contract_payroll_input_id' => $input->id]);
        $this->expectException(\RuntimeException::class);
        $migration->down();
    }

    public function test_run_service_requires_server_side_authorization(): void
    {
        [$actor, $contract] = $this->fixture();
        Gate::define('payroll_calculation_run.create', static fn (): bool => false);

        $this->expectException(AuthorizationException::class);
        app(PayrollCalculationRunService::class)->record($actor, $contract, '2026-01-01', '2026-01-31', 'synthetic-denied-001');
    }

    public function test_blocking_validation_exceptions_are_persisted_and_prevent_payslip_close(): void
    {
        [$actor, $contract, $input, $policy, $paySlip] = $this->fixture(inputStatus: 'unverified');

        $run = app(PayrollCalculationRunService::class)->record($actor, $contract, '2026-01-01', '2026-01-31', 'synthetic-exception-blocked-001', $input);
        $exceptions = $run->exceptions()->get();

        $this->assertTrue($exceptions->contains(fn (PayrollException $exception): bool => $exception->code === 'payroll_input_not_verified' && $exception->severity === PayrollException::SEVERITY_BLOCKING));
        $this->assertTrue($exceptions->contains(fn (PayrollException $exception): bool => $exception->code === 'effective_policy_missing'));
        $this->assertDatabaseHas('payroll_exception_events', [
            'payroll_exception_id' => $exceptions->firstOrFail()->id,
            'event_type' => 'detected',
            'actor_id' => $actor->id,
        ]);
        $this->assertStringNotContainsString('4000.00', $exceptions->firstOrFail()->message);

        try {
            $paySlip->update(['status' => 'Closed']);
            $this->fail('Blocking payroll exceptions must prevent closing a payslip.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
            $this->assertSame('Open', $paySlip->fresh()->status);
        }

        foreach ($exceptions as $exception) {
            app(PayrollValidationService::class)->resolve($actor, $exception, 'Synthetic evidence reviewed and condition corrected.');
        }
        $paySlip->refresh()->update(['status' => 'Closed']);
        $this->assertSame('Closed', $paySlip->fresh()->status);
    }

    public function test_duplicate_period_runs_are_reported_as_warnings_and_can_be_resolved_with_audit_history(): void
    {
        [$actor, $contract, $input, $policy, $paySlip] = $this->fixture();
        $service = app(PayrollCalculationRunService::class);
        $service->record($actor, $contract, '2026-01-01', '2026-01-31', 'synthetic-duplicate-first-001', $input, $policy, $paySlip);
        $second = $service->record($actor, $contract, '2026-01-01', '2026-01-31', 'synthetic-duplicate-second-001', $input, $policy, $paySlip);

        $warning = $second->exceptions()->where('code', 'duplicate_calculation_run')->firstOrFail();
        $this->assertSame(PayrollException::SEVERITY_WARNING, $warning->severity);
        $this->assertSame(PayrollException::STATUS_OPEN, $warning->status);

        $resolved = app(PayrollValidationService::class)->resolve($actor, $warning, 'Confirmed as an intentional synthetic correction run.');
        $this->assertSame(PayrollException::STATUS_RESOLVED, $resolved->status);
        $this->assertSame($actor->id, $resolved->resolved_by);
        $this->assertCount(2, $resolved->events);
        $this->assertSame('resolved', $resolved->events->last()->event_type);
        $this->assertSame('Confirmed as an intentional synthetic correction run.', $resolved->resolution_note);
    }

    public function test_totals_inconsistency_is_a_blocking_exception(): void
    {
        [$actor, $contract, $input, $policy, $paySlip] = $this->fixture();
        $run = app(PayrollCalculationRunService::class)->record($actor, $contract, '2026-01-01', '2026-01-31', 'synthetic-total-check-001', $input, $policy, $paySlip);

        app(PayrollValidationService::class)->recordRun($run, [
            'status' => 'calculated', 'gross_amount' => '100.00', 'deductions_amount' => '10.00', 'net_amount' => '80.00',
            'validation_messages' => [],
        ]);

        $exception = $run->exceptions()->where('code', 'calculation_totals_inconsistent')->firstOrFail();
        $this->assertSame(PayrollException::SEVERITY_BLOCKING, $exception->severity);
    }

    public function test_exception_resolution_requires_server_side_permission(): void
    {
        [$actor, $contract, $input] = $this->fixture(inputStatus: 'unverified');
        $run = app(PayrollCalculationRunService::class)->record($actor, $contract, '2026-01-01', '2026-01-31', 'synthetic-exception-auth-001', $input);
        $exception = $run->exceptions()->firstOrFail();
        Gate::define('payroll_exception.resolve', static fn (): bool => false);

        $this->expectException(AuthorizationException::class);
        app(PayrollValidationService::class)->resolve($actor, $exception, 'Not authorized.');
    }

    public function test_exception_evidence_and_event_history_are_immutable_and_migration_refuses_to_drop_them(): void
    {
        [$actor, $contract, $input] = $this->fixture(inputStatus: 'unverified');
        $run = app(PayrollCalculationRunService::class)->record($actor, $contract, '2026-01-01', '2026-01-31', 'synthetic-exception-retention-001', $input);
        $exception = $run->exceptions()->firstOrFail();
        $event = $exception->events()->firstOrFail();

        try {
            $exception->forceFill(['message' => 'Changed evidence'])->save();
            $this->fail('Payroll exception evidence must be immutable.');
        } catch (LogicException $exceptionError) {
            $this->assertStringContainsString('immutable', $exceptionError->getMessage());
        }

        try {
            $event->forceFill(['note' => 'Changed history'])->save();
            $this->fail('Payroll exception events must be immutable.');
        } catch (LogicException $exceptionError) {
            $this->assertStringContainsString('immutable', $exceptionError->getMessage());
        }

        $exception->refresh();
        try {
            $exception->forceFill(['status' => PayrollException::STATUS_RESOLVED])->save();
            $this->fail('Exceptions cannot be resolved without audited resolution metadata.');
        } catch (LogicException $exceptionError) {
            $this->assertStringContainsString('actor, timestamp, and note', $exceptionError->getMessage());
        }

        $migration = require base_path('database/migrations/2026_10_30_000002_create_payroll_exceptions_table.php');
        $this->expectException(\RuntimeException::class);
        $migration->down();
    }

    private function makeVerifiedInput(User $actor, EmployeeContract $contract, string $start, string $end, string $baseAmount, string $allowanceAmount): EmployeeContractPayrollInput
    {
        return EmployeeContractPayrollInput::query()->create([
            'employee_contract_id' => $contract->id,
            'effective_from' => $start, 'effective_until' => $end,
            'salary_basis' => 'monthly', 'base_amount' => $baseAmount, 'currency_code' => 'RON',
            'scheduled_hours_per_week' => 40, 'work_schedule' => ['pattern' => 'synthetic_weekday'],
            'allowance_inputs' => [['code' => 'synthetic_allowance', 'amount' => $allowanceAmount, 'currency_code' => 'RON']],
            'input_status' => 'verified', 'reviewed_by' => $actor->id, 'reviewed_at' => now(),
        ]);
    }

    /** @return array{User, EmployeeContract, EmployeeContractPayrollInput, PayrollPolicy, EmployeePaySlip} */
    private function fixture(string $inputStatus = 'verified', bool $incomplete = false, bool $calculablePolicy = false, bool $prorate = false, string $contractStart = '2026-01-01'): array
    {
        $actor = User::factory()->create();
        $contract = EmployeeContract::factory()->create([
            'start_date' => $contractStart, 'end_date' => '2027-12-31', 'contract_type' => 'full_time',
            'base_salary' => 4000, 'hourly_rate' => 25,
        ]);
        $input = EmployeeContractPayrollInput::query()->create([
            'employee_contract_id' => $contract->id,
            'effective_from' => $contractStart, 'effective_until' => '2026-12-31',
            'salary_basis' => $incomplete ? null : 'monthly', 'base_amount' => $incomplete ? null : '4000.00', 'currency_code' => $incomplete ? null : 'RON',
            'scheduled_hours_per_week' => $incomplete ? null : 40,
            'work_schedule' => ['pattern' => 'synthetic_weekday'],
            'allowance_inputs' => [['code' => 'synthetic_allowance', 'amount' => '100.00', 'currency_code' => 'RON']],
            'input_status' => $inputStatus,
            'reviewed_by' => $inputStatus === 'verified' ? $actor->id : null,
            'reviewed_at' => $inputStatus === 'verified' ? now() : null,
        ]);
        $policyParameters = $calculablePolicy ? [
            'base_pay' => ['type' => 'input_amount', 'input_field' => 'base_amount', 'expected_salary_basis' => 'monthly', 'item_type' => 'earning', 'label' => 'Synthetic base pay'],
            'transport' => ['type' => 'input_allowance', 'allowance_code' => 'synthetic_allowance', 'amount_basis' => 'monthly_amount', 'item_type' => 'earning', 'label' => 'Synthetic allowance'],
            'bonus' => ['type' => 'percentage_of', 'base_item_code' => 'base_pay', 'rate_percent' => '10', 'item_type' => 'earning', 'label' => 'Synthetic bonus'],
            'deduction' => ['type' => 'percentage_of', 'base_item_code' => 'base_pay', 'rate_percent' => '5', 'item_type' => 'deduction', 'label' => 'Synthetic deduction'],
        ] : ['base_salary' => 'configured-only'];
        $policy = PayrollPolicy::factory()->create([
            'policy_key' => 'synthetic_policy', 'version' => 1, 'status' => 'active',
            'effective_from' => '2026-01-01', 'effective_until' => '2026-12-31',
            'parameters' => $policyParameters,
            'required_parameters' => array_keys($policyParameters), 'calculation_order' => array_keys($policyParameters),
            'contract_rules' => ['full_time' => ['supported_salary_bases' => ['monthly', 'annual']]],
            'proration_rule' => $prorate ? ['method' => 'calendar_days'] : null,
            'rounding_mode' => 'half_up', 'rounding_precision' => 2,
        ]);
        $paySlip = EmployeePaySlip::factory()->create([
            'employee_id' => $contract->employee_id, 'month' => '2026-01-01',
        ]);

        return [$actor, $contract, $input, $policy, $paySlip];
    }
}
