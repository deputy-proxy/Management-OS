<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\EmployeePaySlip;
use App\Models\PayrollPeriodInput;
use App\Models\User;
use App\Services\Payroll\PayrollCalculationRunService;
use App\Services\Payroll\PayrollPeriodInputService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class PayrollPeriodInputTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['payroll_period_input.create', 'payroll_period_input.submit', 'payroll_period_input.approve', 'payroll_calculation_run.create'] as $ability) {
            Gate::define($ability, static fn (): bool => true);
        }
    }

    public function test_input_capture_requires_valid_period_and_effective_dates_and_rejects_duplicate_source_links(): void
    {
        $employee = Employee::factory()->create();
        $contract = EmployeeContract::factory()->create(['employee_id' => $employee->id, 'start_date' => '2026-01-01', 'end_date' => '2027-12-31']);
        $base = ['employee_id' => $employee->id, 'employee_contract_id' => $contract->id, 'period_start' => '2026-01-01', 'period_end' => '2026-01-31', 'effective_from' => '2026-01-01', 'category' => 'leave', 'entry_type' => 'earning', 'description' => 'Synthetic overtime', 'quantity' => '2', 'unit' => 'hour', 'source_type' => 'timesheet', 'source_reference' => 'SYN-001'];
        $first = PayrollPeriodInput::query()->create($base);
        $this->assertSame('draft', $first->status);
        try {
            PayrollPeriodInput::query()->create($base);
            $this->fail('A source record must not be duplicated in a payroll period.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('source_reference', $exception->errors());
        }
        try {
            PayrollPeriodInput::query()->create(array_merge($base, ['period_end' => '2026-02-01']));
            $this->fail('Payroll inputs must be tied to one full calendar month.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('period_end', $exception->errors());
        }
        $this->assertDatabaseHas('payroll_period_inputs', ['id' => $first->id, 'status' => 'draft']);
        try {
            PayrollPeriodInput::query()->create(array_merge($base, ['category' => 'absence', 'source_reference' => 'SYN-ABS-002', 'effective_from' => '2026-01-13', 'effective_until' => '2026-01-16']));
            $this->fail('Overlapping leave and absence date ranges must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('effective_from', $exception->errors());
        }
    }

    public function test_status_transitions_cannot_be_applied_without_the_authenticated_permission(): void
    {
        $input = $this->input();
        auth()->logout();
        try {
            $input->status = 'submitted';
            $input->save();
            $this->fail('Submitting a record must require an authenticated actor with permission.');
        } catch (AuthorizationException) {
            $this->assertSame('draft', $input->fresh()->status);
        }
    }

    public function test_submission_approval_and_rejection_record_reviewer_and_keep_approved_facts_immutable(): void
    {
        $actor = User::factory()->create();
        $input = $this->input();
        $this->actingAs($actor);
        $service = app(PayrollPeriodInputService::class);
        $service->submit($actor, $input);
        $approved = $service->approve($actor, $input->fresh(), 'Synthetic source checked');
        $this->assertSame('approved', $approved->status);
        $this->assertSame($actor->id, $approved->reviewed_by);
        $this->assertNotNull($approved->reviewed_at);
        try {
            $approved->update(['amount' => '999.00']);
            $this->fail('Approved input facts must be immutable.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('immutable', $exception->getMessage());
        }

        $rejected = $this->input(['source_reference' => 'SYN-002']);
        $service->submit($actor, $rejected);
        $service->reject($actor, $rejected->fresh(), 'Source does not reconcile');
        $this->assertSame('rejected', $rejected->fresh()->status);
        $this->assertSame('Source does not reconcile', $rejected->fresh()->review_note);
    }

    public function test_approved_input_is_snapshotted_and_calculation_is_blocked_without_specialist_policy(): void
    {
        $actor = User::factory()->create();
        $this->actingAs($actor);
        $input = $this->input();
        $service = app(PayrollPeriodInputService::class);
        $service->submit($actor, $input);
        $service->approve($actor, $input->fresh());
        $run = app(PayrollCalculationRunService::class)->record($actor, $input->employeeContract, '2026-01-01', '2026-01-31', 'period-input-snapshot-001');
        $this->assertSame('approved', $run->input_snapshot['period_inputs'][0]['status']);
        $this->assertSame($input->id, $run->input_snapshot['period_inputs'][0]['id']);
        $this->assertContains('period_input_policy_unapproved', $run->blocked_reasons);
        $this->assertSame('blocked', $run->result->result_status);
        $this->assertNull($run->result->net_amount);
    }

    public function test_corrections_create_a_new_draft_linked_to_original_without_mutating_it(): void
    {
        $actor = User::factory()->create();
        $this->actingAs($actor);
        $original = $this->input();
        $service = app(PayrollPeriodInputService::class);
        $service->submit($actor, $original);
        $service->approve($actor, $original->fresh());
        $corrected = $service->correct($actor, $original->fresh(), ['amount' => '150.00', 'source_reference' => 'SYN-CORR-001']);
        $this->assertSame($original->id, $corrected->corrects_id);
        $this->assertSame('draft', $corrected->status);
        $this->assertSame('100.000000', (string) $original->fresh()->amount);
        $this->assertSame('150.000000', (string) $corrected->amount);
        $service->submit($actor, $corrected);
        $service->approve($actor, $corrected->fresh());
        $run = app(PayrollCalculationRunService::class)->record($actor, $corrected->employeeContract, '2026-01-01', '2026-01-31', 'period-input-correction-001');
        $this->assertCount(1, $run->input_snapshot['period_inputs']);
        $this->assertSame($corrected->id, $run->input_snapshot['period_inputs'][0]['id']);
    }

    public function test_migration_refuses_to_drop_recorded_payroll_period_inputs(): void
    {
        $this->input();
        $migration = require base_path('database/migrations/2026_10_30_000001_create_payroll_period_inputs_table.php');
        $this->expectException(\RuntimeException::class);
        $migration->down();
    }

    public function test_closed_payroll_period_rejects_late_inputs(): void
    {
        $employee = Employee::factory()->create();
        $contract = EmployeeContract::factory()->create(['employee_id' => $employee->id, 'start_date' => '2026-01-01', 'end_date' => '2027-12-31']);
        $slip = EmployeePaySlip::factory()->create(['employee_id' => $employee->id, 'month' => '2026-01-01']);
        DB::table('employee_pay_slips')->where('id', $slip->id)->update(['status' => 'Closed', 'approval_status' => 'legacy_closed']);
        $this->expectException(ValidationException::class);
        PayrollPeriodInput::query()->create([
            'employee_id' => $employee->id, 'employee_contract_id' => $contract->id,
            'period_start' => '2026-01-01', 'period_end' => '2026-01-31', 'effective_from' => '2026-01-15',
            'category' => 'bonus', 'entry_type' => 'earning', 'description' => 'Late synthetic bonus',
            'amount' => '25.00', 'currency_code' => 'RON', 'source_type' => 'bonus_register', 'source_reference' => 'SYN-LATE-001',
        ]);
    }

    private function input(array $overrides = []): PayrollPeriodInput
    {
        $employee = Employee::factory()->create();
        $contract = EmployeeContract::factory()->create(['employee_id' => $employee->id, 'start_date' => '2026-01-01', 'end_date' => '2027-12-31']);

        return PayrollPeriodInput::query()->create(array_merge([
            'employee_id' => $employee->id, 'employee_contract_id' => $contract->id,
            'period_start' => '2026-01-01', 'period_end' => '2026-01-31', 'effective_from' => '2026-01-12', 'effective_until' => '2026-01-14',
            'category' => 'leave', 'entry_type' => 'informational', 'description' => 'Synthetic leave record',
            'quantity' => '3', 'unit' => 'day', 'amount' => '100.00', 'currency_code' => 'RON',
            'source_type' => 'leave_register', 'source_reference' => 'SYN-001', 'status' => 'draft',
        ], $overrides));
    }
}
