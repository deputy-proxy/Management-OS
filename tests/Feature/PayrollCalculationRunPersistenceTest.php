<?php

namespace Tests\Feature;

use App\Models\EmployeeContract;
use App\Models\EmployeeContractPayrollInput;
use App\Models\EmployeePaySlip;
use App\Models\PayrollCalculationRun;
use App\Models\PayrollPolicy;
use App\Models\User;
use App\Services\Payroll\PayrollCalculationRunService;
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
    }

    public function test_ready_run_persists_versioned_policy_and_explicit_input_snapshots_without_calculating_payroll(): void
    {
        [$actor, $contract, $input, $policy, $paySlip] = $this->fixture();

        $run = app(PayrollCalculationRunService::class)->record($actor, $contract, '2026-01-01', '2026-01-31', 'synthetic-run-001', $input, $policy, $paySlip);

        $this->assertSame(PayrollCalculationRun::STATUS_READY, $run->run_status);
        $this->assertSame([], $run->blocked_reasons);
        $this->assertSame($input->id, $run->input_snapshot['payroll_input']['id']);
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
    }

    public function test_verified_but_incomplete_inputs_are_blocked_instead_of_filling_unknown_values(): void
    {
        [$actor, $contract, $input, $policy] = $this->fixture(incomplete: true);

        $run = app(PayrollCalculationRunService::class)->record($actor, $contract, '2026-01-01', '2026-01-31', 'synthetic-incomplete-001', $input, $policy);

        $this->assertSame(PayrollCalculationRun::STATUS_BLOCKED, $run->run_status);
        $this->assertContains('payroll_input_incomplete', $run->blocked_reasons);
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

    public function test_run_service_requires_server_side_authorization(): void
    {
        [$actor, $contract] = $this->fixture();
        Gate::define('payroll_calculation_run.create', static fn (): bool => false);

        $this->expectException(AuthorizationException::class);
        app(PayrollCalculationRunService::class)->record($actor, $contract, '2026-01-01', '2026-01-31', 'synthetic-denied-001');
    }

    /** @return array{User, EmployeeContract, EmployeeContractPayrollInput, PayrollPolicy, EmployeePaySlip} */
    private function fixture(string $inputStatus = 'verified', bool $incomplete = false): array
    {
        $actor = User::factory()->create();
        $contract = EmployeeContract::factory()->create([
            'start_date' => '2026-01-01', 'end_date' => '2027-12-31',
            'base_salary' => 4000, 'hourly_rate' => 25,
        ]);
        $input = EmployeeContractPayrollInput::query()->create([
            'employee_contract_id' => $contract->id,
            'effective_from' => '2026-01-01', 'effective_until' => '2026-12-31',
            'salary_basis' => $incomplete ? null : 'monthly', 'currency_code' => $incomplete ? null : 'RON',
            'scheduled_hours_per_week' => $incomplete ? null : 40,
            'work_schedule' => ['pattern' => 'synthetic_weekday'],
            'allowance_inputs' => [['code' => 'synthetic_allowance', 'amount' => '100.00', 'currency_code' => 'RON']],
            'input_status' => $inputStatus,
            'reviewed_by' => $inputStatus === 'verified' ? $actor->id : null,
            'reviewed_at' => $inputStatus === 'verified' ? now() : null,
        ]);
        $policy = PayrollPolicy::factory()->create([
            'policy_key' => 'synthetic_policy', 'version' => 1, 'status' => 'active',
            'effective_from' => '2026-01-01', 'effective_until' => '2026-12-31',
            'parameters' => ['base_salary' => 'configured-only'],
            'required_parameters' => ['base_salary'], 'calculation_order' => ['base_salary'],
            'rounding_mode' => 'half_up', 'rounding_precision' => 2,
        ]);
        $paySlip = EmployeePaySlip::factory()->create([
            'employee_id' => $contract->employee_id, 'month' => '2026-01-01',
        ]);

        return [$actor, $contract, $input, $policy, $paySlip];
    }
}
