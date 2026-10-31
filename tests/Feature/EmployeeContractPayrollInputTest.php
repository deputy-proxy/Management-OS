<?php

namespace Tests\Feature;

use App\Models\EmployeeContract;
use App\Models\EmployeeContractPayrollInput;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class EmployeeContractPayrollInputTest extends TestCase
{
    use RefreshDatabase;

    public function test_contract_can_store_effective_dated_payroll_inputs_without_mutating_legacy_contract_fields(): void
    {
        $contract = EmployeeContract::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2027-12-31', 'base_salary' => 3500, 'hourly_rate' => 0]);

        $input = EmployeeContractPayrollInput::query()->create([
            'employee_contract_id' => $contract->id,
            'effective_from' => '2026-01-01',
            'effective_until' => '2026-06-30',
            'salary_basis' => 'monthly',
            'currency_code' => 'RON',
            'scheduled_hours_per_week' => 40,
            'work_schedule' => ['days_per_week' => '5', 'pattern' => 'weekday'],
            'allowance_inputs' => [['code' => 'synthetic_allowance', 'amount' => '125.00', 'currency_code' => 'RON']],
            'tax_status_code' => null,
            'input_status' => 'unverified',
        ]);

        $this->assertSame('3500.00', (string) $contract->fresh()->base_salary);
        $this->assertSame('RON', $input->fresh()->currency_code);
        $this->assertSame('125.00', (string) $input->fresh()->allowance_inputs[0]['amount']);
        $this->assertFalse($input->isCompleteForCalculation());
        $this->assertTrue($contract->payrollInputs()->whereKey($input->id)->exists());
    }

    public function test_effective_dated_inputs_allow_adjacent_periods_but_reject_overlapping_periods(): void
    {
        $contract = EmployeeContract::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2027-12-31']);
        $base = [
            'employee_contract_id' => $contract->id,
            'salary_basis' => 'monthly',
            'currency_code' => 'RON',
            'scheduled_hours_per_week' => 40,
            'input_status' => 'unverified',
        ];
        EmployeeContractPayrollInput::query()->create($base + [
            'effective_from' => '2026-01-01',
            'effective_until' => '2026-06-30',
        ]);
        EmployeeContractPayrollInput::query()->create($base + [
            'effective_from' => '2026-07-01',
            'effective_until' => null,
        ]);

        try {
            EmployeeContractPayrollInput::query()->create($base + [
                'effective_from' => '2026-06-01',
                'effective_until' => '2026-07-15',
            ]);
            $this->fail('Overlapping effective periods must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('effective_from', $exception->errors());
        }
    }

    public function test_effective_scope_and_unknown_inputs_are_not_treated_as_calculable(): void
    {
        $contract = EmployeeContract::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2027-12-31']);
        $input = EmployeeContractPayrollInput::query()->create([
            'employee_contract_id' => $contract->id,
            'effective_from' => '2026-01-01',
            'effective_until' => '2026-03-31',
            'input_status' => 'unverified',
        ]);

        $this->assertSame(1, EmployeeContractPayrollInput::query()->effectiveOn('2026-02-01')->count());
        $this->assertSame(0, EmployeeContractPayrollInput::query()->effectiveOn('2026-04-01')->count());
        $this->assertFalse($input->isCompleteForCalculation());
    }

    public function test_input_period_must_be_contained_by_the_contract(): void
    {
        $contract = EmployeeContract::factory()->create(['start_date' => '2026-02-01', 'end_date' => '2026-11-30']);

        $this->expectException(ValidationException::class);
        EmployeeContractPayrollInput::query()->create([
            'employee_contract_id' => $contract->id,
            'effective_from' => '2026-01-01',
            'effective_until' => '2026-06-30',
            'input_status' => 'unverified',
        ]);
    }

    public function test_activity_log_does_not_store_sensitive_input_payloads(): void
    {
        $contract = EmployeeContract::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2027-12-31']);
        $input = EmployeeContractPayrollInput::query()->create([
            'employee_contract_id' => $contract->id,
            'effective_from' => '2026-01-01',
            'base_amount' => '4321.00',
            'tax_status_code' => 'synthetic-private-code',
            'source_reference' => 'synthetic-private-reference',
            'allowance_inputs' => [['code' => 'synthetic-private-allowance', 'amount' => '100.00', 'currency_code' => 'RON']],
            'input_status' => 'unverified',
        ]);

        $activity = $input->activities()->where('description', 'Sensitive payroll input fields changed')->latest('id')->firstOrFail();
        $properties = json_encode($activity->properties->toArray());

        $this->assertStringNotContainsString('4321.000000', $properties);
        $this->assertStringNotContainsString('synthetic-private-code', $properties);
        $this->assertStringNotContainsString('synthetic-private-reference', $properties);
        $this->assertStringNotContainsString('synthetic-private-allowance', $properties);
        $this->assertContains('tax_status_code', $activity->properties['changed_fields']);
        $this->assertContains('allowance_inputs', $activity->properties['changed_fields']);
    }

    public function test_currency_salary_basis_and_allowance_values_are_validated(): void
    {
        $contract = EmployeeContract::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2027-12-31']);

        $this->expectException(ValidationException::class);
        EmployeeContractPayrollInput::query()->create([
            'employee_contract_id' => $contract->id,
            'effective_from' => '2026-01-01',
            'salary_basis' => 'guessed-tax-basis',
            'currency_code' => 'ron',
            'scheduled_hours_per_week' => 200,
            'allowance_inputs' => [['code' => '', 'amount' => -1, 'currency_code' => 'RON']],
            'input_status' => 'unverified',
        ]);
    }

    public function test_verified_inputs_require_a_reviewer_and_timestamp(): void
    {
        $contract = EmployeeContract::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2027-12-31']);

        $this->expectException(ValidationException::class);
        EmployeeContractPayrollInput::query()->create([
            'employee_contract_id' => $contract->id,
            'effective_from' => '2026-01-01',
            'input_status' => 'verified',
        ]);
    }

    public function test_soft_deletion_preserves_input_history_and_permanent_deletion_is_blocked(): void
    {
        $contract = EmployeeContract::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2027-12-31']);
        $input = EmployeeContractPayrollInput::query()->create([
            'employee_contract_id' => $contract->id,
            'effective_from' => '2026-01-01',
            'input_status' => 'unverified',
        ]);

        $input->delete();
        $this->assertSoftDeleted('employee_contract_payroll_inputs', ['id' => $input->id]);
        $this->assertDatabaseHas('employee_contract_payroll_inputs', ['id' => $input->id]);

        $this->expectException(LogicException::class);
        $input->forceDelete();
    }

    public function test_verified_inputs_cannot_be_changed_except_to_shorten_their_effective_period(): void
    {
        $contract = EmployeeContract::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2027-12-31']);
        $reviewer = User::factory()->create();
        $input = EmployeeContractPayrollInput::query()->create([
            'employee_contract_id' => $contract->id,
            'effective_from' => '2026-01-01',
            'effective_until' => null,
            'salary_basis' => 'monthly',
            'currency_code' => 'RON',
            'scheduled_hours_per_week' => 40,
            'input_status' => 'verified',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        try {
            $input->update(['currency_code' => 'EUR']);
            $this->fail('Verified input facts must not be overwritten.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('immutable', $exception->getMessage());
        }

        $input->refresh();
        $input->update(['effective_until' => '2026-06-30']);
        $this->assertSame('2026-06-30', $input->fresh()->effective_until->toDateString());
    }

    public function test_verified_input_records_actor_and_timestamp(): void
    {
        $contract = EmployeeContract::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2027-12-31']);
        $reviewer = User::factory()->create();
        $input = EmployeeContractPayrollInput::query()->create([
            'employee_contract_id' => $contract->id,
            'effective_from' => '2026-01-01',
            'salary_basis' => 'monthly',
            'base_amount' => '4000.00',
            'currency_code' => 'RON',
            'scheduled_hours_per_week' => 40,
            'input_status' => 'verified',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        $this->assertTrue($input->isCompleteForCalculation());
        $this->assertSame($reviewer->id, $input->reviewer->id);
    }
}
