<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\EmployeeHoliday;
use App\Models\EmployeePaySlip;
use App\Models\Session;
use App\Models\SessionAssistance;
use App\Models\TestSimulation;
use App\Services\EmployeePaySlipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class EmployeePaySlipWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_generation_is_idempotent_and_includes_approved_holiday_and_equipment_reimbursement(): void
    {
        $employee = Employee::factory()->create();
        $employeeWithoutContract = Employee::factory()->create();
        $contract = EmployeeContract::factory()->create([
            'employee_id' => $employee->id,
            'start_date' => '2024-02-01',
            'end_date' => '2024-12-31',
            'hourly_rate' => 25,
            'clauses' => [[
                'type' => 'use_own_equipment_reimbursement',
                'data' => ['amount' => 150],
            ]],
        ]);
        EmployeeHoliday::factory()->create([
            'employee_id' => $employee->id,
            'start_date' => '2024-02-28',
            'end_date' => '2024-02-29',
            'hours_per_day' => 8,
            'status' => 'Approved',
        ]);

        $this->artisan('employee-payslips:generate', ['--month' => '2024-02'])->assertSuccessful();
        $contract->update(['clauses' => [['type' => 'use_own_equipment_reimbursement', 'data' => ['amount' => 200]]]]);
        $this->artisan('employee-payslips:generate', ['--month' => '2024-02'])->assertSuccessful();

        $paySlip = EmployeePaySlip::query()->where('employee_id', $employee->id)->firstOrFail();
        $this->assertSame('2024-02-01', $paySlip->month->toDateString());
        $this->assertSame('Open', $paySlip->status);
        $this->assertNull($paySlip->due_on);
        $this->assertNull($paySlip->paid_on);
        $this->assertSame(2, $paySlip->lineItems()->count());
        $this->assertDatabaseMissing('employee_pay_slips', ['employee_id' => $employeeWithoutContract->id, 'month' => '2024-02-01']);
        $this->assertSame('550.00', $paySlip->amount);
        $this->assertSame('400.00', $paySlip->lineItems()->where('item_type', 'holiday')->value('amount'));
        $this->assertSame('150.00', $paySlip->lineItems()->where('item_type', 'equipment_reimbursement')->value('amount'));
        $this->assertSame(150, $paySlip->lineItems()->where('item_type', 'equipment_reimbursement')->firstOrFail()->calculation_snapshot['amount']);
        $this->assertSame('use_own_equipment_reimbursement', $paySlip->lineItems()->where('item_type', 'equipment_reimbursement')->firstOrFail()->calculation_snapshot['clause_key']);
    }

    public function test_session_item_uses_a_precise_rate_snapshot_and_repeated_action_does_not_duplicate_it(): void
    {
        $employee = Employee::factory()->create();
        $contract = EmployeeContract::factory()->create([
            'employee_id' => $employee->id,
            'start_date' => '2026-01-01',
            'end_date' => '2027-12-31',
            'hourly_rate' => 33.33,
            'clauses' => [],
        ]);
        $session = Session::factory()->individual()->create([
            'teacher_id' => $employee->id,
            'starts_at' => '2026-10-10 09:00:00',
            'ends_at' => '2026-10-10 10:30:00',
            'status' => 'completed',
            'identifier' => 'Mock lesson',
        ]);

        $service = app(EmployeePaySlipService::class);
        $first = $service->addSession($session);
        $contract->update(['hourly_rate' => 50]);
        $second = $service->addSession($session->fresh());

        $this->assertTrue($first['created']);
        $this->assertFalse($second['created']);
        $this->assertSame(1, EmployeePaySlip::query()->where('employee_id', $employee->id)->firstOrFail()->lineItems()->count());
        $item = $first['item']->fresh();
        $this->assertSame('1.50', $item->quantity);
        $this->assertSame('33.33', $item->unit_rate);
        $this->assertSame('50.00', $item->amount);
        $this->assertSame('33.33', (string) $item->calculation_snapshot['hourly_rate']);
        $paySlip = EmployeePaySlip::query()->where('employee_id', $employee->id)->firstOrFail();
        $this->assertSame('50.00', $paySlip->amount);
        $paySlip->update(['manual_amount' => 100]);
        $this->assertSame('150.00', $paySlip->fresh()->amount);
        $otherEmployee = Employee::factory()->create();
        EmployeeContract::factory()->create([
            'employee_id' => $otherEmployee->id,
            'start_date' => '2026-01-01',
            'end_date' => '2027-12-31',
            'hourly_rate' => 40,
            'clauses' => [],
        ]);
        $session->update(['teacher_id' => $otherEmployee->id]);
        try {
            $service->addSession($session->fresh());
            $this->fail('A source record must not be paid to a second employee.');
        } catch (ValidationException) {
            $this->assertSame(1, EmployeePaySlip::query()->count());
        }
    }

    public function test_assistance_and_completed_test_simulation_can_be_added_to_the_same_monthly_slip(): void
    {
        $employee = Employee::factory()->create();
        EmployeeContract::factory()->create([
            'employee_id' => $employee->id,
            'start_date' => '2026-01-01',
            'end_date' => '2027-12-31',
            'hourly_rate' => 20,
            'clauses' => [],
        ]);
        $session = Session::factory()->create([
            'teacher_id' => $employee->id,
            'starts_at' => '2026-10-10 09:00:00',
            'ends_at' => '2026-10-10 10:00:00',
            'status' => 'completed',
        ]);
        $assistance = SessionAssistance::factory()->create([
            'session_id' => $session->id,
            'teacher_id' => $employee->id,
            'assistance_date' => '2026-10-10',
            'status' => 'complete',
        ]);
        $simulation = TestSimulation::factory()->create([
            'teacher_id' => $employee->id,
            'date' => '2026-10-12 10:00:00',
            'number_of_hours' => 2.25,
            'status' => 'scheduled',
            'title' => 'Mock exam',
        ]);

        $service = app(EmployeePaySlipService::class);
        $assistanceResult = $service->addSessionAssistance($assistance);
        $itemsBeforeScheduledSimulation = EmployeePaySlip::query()->where('employee_id', $employee->id)->firstOrFail()->lineItems()->count();
        try {
            $service->addTestSimulation($simulation);
            $this->fail('Scheduled test simulations cannot be paid before completion.');
        } catch (ValidationException) {
            $this->assertSame($itemsBeforeScheduledSimulation, EmployeePaySlip::query()->where('employee_id', $employee->id)->firstOrFail()->lineItems()->count());
        }
        $simulation->update(['status' => 'completed']);
        $simulationResult = $service->addTestSimulation($simulation->fresh());

        $this->assertTrue($assistanceResult['created']);
        $this->assertSame('20.00', $assistanceResult['item']->amount);
        $this->assertTrue($simulationResult['created']);
        $this->assertSame('45.00', $simulationResult['item']->amount);
        $this->assertSame('65.00', EmployeePaySlip::query()->where('employee_id', $employee->id)->firstOrFail()->amount);
    }

    public function test_cancelled_sessions_are_rejected(): void
    {
        $employee = Employee::factory()->create();
        EmployeeContract::factory()->create(['employee_id' => $employee->id, 'start_date' => '2026-01-01', 'end_date' => '2027-12-31']);
        $session = Session::factory()->create([
            'teacher_id' => $employee->id,
            'starts_at' => '2026-10-10 09:00:00',
            'ends_at' => '2026-10-10 10:00:00',
            'status' => 'cancelled',
        ]);

        $this->expectException(ValidationException::class);
        app(EmployeePaySlipService::class)->addSession($session);
    }

    public function test_source_activities_without_an_active_contract_are_rejected(): void
    {
        $employee = Employee::factory()->create();
        $session = Session::factory()->create([
            'teacher_id' => $employee->id,
            'starts_at' => '2026-10-10 09:00:00',
            'ends_at' => '2026-10-10 10:00:00',
            'status' => 'completed',
        ]);

        $this->expectException(ValidationException::class);
        app(EmployeePaySlipService::class)->addSession($session);
    }

    public function test_paid_slips_are_locked_and_corrections_are_signed_adjustments_on_a_later_period(): void
    {
        $employee = Employee::factory()->create();
        EmployeeContract::factory()->create([
            'employee_id' => $employee->id,
            'start_date' => '2026-01-01',
            'end_date' => '2027-12-31',
            'hourly_rate' => 20,
            'clauses' => [],
        ]);
        $session = Session::factory()->create([
            'teacher_id' => $employee->id,
            'starts_at' => '2026-10-10 09:00:00',
            'ends_at' => '2026-10-10 10:00:00',
            'status' => 'completed',
        ]);
        $service = app(EmployeePaySlipService::class);
        $original = $service->addSession($session)['item'];
        $octoberSlip = $original->paySlip;
        $octoberSlip->update(['status' => 'Closed']);
        try {
            $octoberSlip->delete();
            $this->fail('A closed payslip with source items must not be deleted.');
        } catch (ValidationException) {
            $this->assertFalse($octoberSlip->fresh()->trashed());
        }

        $nextMonthSlip = EmployeePaySlip::factory()->create([
            'employee_id' => $employee->id,
            'month' => '2026-11-01',
            'manual_amount' => 0,
            'status' => 'Open',
        ]);
        try {
            $nextMonthSlip->update(['status' => 'Paid', 'paid_on' => '2026-11-30']);
            $this->fail('An Open payslip cannot skip the Closed status.');
        } catch (ValidationException) {
            $this->assertSame('Open', $nextMonthSlip->fresh()->status);
        }
        $adjustment = $service->addAdjustment($nextMonthSlip, $original, -5.50, 'Correct overpaid session rate');

        $this->assertTrue($adjustment['created']);
        $this->assertSame('-5.50', $adjustment['item']->amount);
        $this->assertSame('20.00', $original->fresh()->amount);

        $nextMonthSlip->refresh()->update(['status' => 'Closed']);
        try {
            $nextMonthSlip->refresh()->update(['status' => 'Paid']);
            $this->fail('A paid payslip must have a payment date.');
        } catch (ValidationException) {
            $this->assertSame('Closed', $nextMonthSlip->fresh()->status);
        }
        $nextMonthSlip->refresh()->update(['status' => 'Paid', 'paid_on' => '2026-11-30']);
        $this->expectException(ValidationException::class);
        $nextMonthSlip->refresh()->update(['manual_amount' => 1]);
    }

    public function test_legacy_breakdown_and_manual_baseline_remain_separate_from_source_items_and_locked_states(): void
    {
        $employee = Employee::factory()->create();
        EmployeeContract::factory()->create([
            'employee_id' => $employee->id,
            'start_date' => '2026-01-01',
            'end_date' => '2027-12-31',
            'hourly_rate' => 20,
            'clauses' => [],
        ]);
        $legacyBreakdown = [
            ['type' => 'gross_salary', 'amount' => 900, 'notes' => 'Manually verified baseline'],
            ['type' => 'cas', 'base_amount' => 900, 'amount' => 225],
        ];
        $paySlip = EmployeePaySlip::factory()->create([
            'employee_id' => $employee->id,
            'month' => '2026-10-01',
            'items' => $legacyBreakdown,
            'manual_amount' => 123.45,
            'status' => 'Open',
        ]);

        $session = Session::factory()->create([
            'teacher_id' => $employee->id,
            'starts_at' => '2026-10-10 09:00:00',
            'ends_at' => '2026-10-10 10:00:00',
            'status' => 'completed',
        ]);
        app(EmployeePaySlipService::class)->addSession($session);

        $paySlip->refresh();
        $this->assertSame($legacyBreakdown, $paySlip->items);
        $this->assertSame('123.45', $paySlip->manual_amount);
        $this->assertSame('143.45', $paySlip->amount);
        $this->assertSame(1, $paySlip->lineItems()->count());

        $paySlip->update(['status' => 'Closed']);
        try {
            $paySlip->update(['items' => [], 'manual_amount' => 0]);
            $this->fail('A closed payslip must preserve its legacy breakdown and baseline.');
        } catch (ValidationException) {
            $this->assertSame($legacyBreakdown, $paySlip->fresh()->items);
            $this->assertSame('123.45', $paySlip->fresh()->manual_amount);
            $this->assertSame('143.45', $paySlip->fresh()->amount);
        }

        $paySlip->refresh()->update(['status' => 'Paid', 'paid_on' => '2026-10-31']);
        try {
            $paySlip->refresh()->update(['items' => []]);
            $this->fail('A paid payslip must preserve its legacy breakdown.');
        } catch (ValidationException) {
            $this->assertSame($legacyBreakdown, $paySlip->fresh()->items);
            $this->assertSame('123.45', $paySlip->fresh()->manual_amount);
            $this->assertSame('143.45', $paySlip->fresh()->amount);
        }
    }

    public function test_source_line_items_cannot_be_mutated_or_deleted(): void
    {
        $employee = Employee::factory()->create();
        EmployeeContract::factory()->create([
            'employee_id' => $employee->id,
            'start_date' => '2026-01-01',
            'end_date' => '2027-12-31',
            'hourly_rate' => 20,
            'clauses' => [],
        ]);
        $session = Session::factory()->create([
            'teacher_id' => $employee->id,
            'starts_at' => '2026-10-10 09:00:00',
            'ends_at' => '2026-10-10 10:00:00',
            'status' => 'completed',
        ]);
        $item = app(EmployeePaySlipService::class)->addSession($session)['item'];

        try {
            $item->update(['amount' => 0]);
            $this->fail('A source item must not be mutable.');
        } catch (LogicException) {
            $this->assertSame('20.00', $item->fresh()->amount);
        }

        $this->expectException(LogicException::class);
        $item->delete();
    }

    public function test_failed_outer_transaction_rolls_back_the_payslip_and_source_item(): void
    {
        $employee = Employee::factory()->create();
        EmployeeContract::factory()->create([
            'employee_id' => $employee->id,
            'start_date' => '2026-01-01',
            'end_date' => '2027-12-31',
            'hourly_rate' => 20,
            'clauses' => [],
        ]);
        $session = Session::factory()->create([
            'teacher_id' => $employee->id,
            'starts_at' => '2026-10-10 09:00:00',
            'ends_at' => '2026-10-10 10:00:00',
            'status' => 'completed',
        ]);

        try {
            DB::transaction(function () use ($session): void {
                app(EmployeePaySlipService::class)->addSession($session);
                throw new \RuntimeException('Simulate a later workflow failure.');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulate a later workflow failure.', $exception->getMessage());
        }

        $this->assertDatabaseMissing('employee_pay_slips', ['employee_id' => $employee->id, 'month' => '2026-10-01']);
        $this->assertDatabaseCount('employee_pay_slip_items', 0);
    }

    public function test_legacy_misspelled_equipment_clause_key_is_still_readable(): void
    {
        $contract = EmployeeContract::factory()->create([
            'clauses' => ['UseOwnEquipmentReimbursment' => ['amount' => 125.5]],
        ]);

        $this->assertSame(125.5, $contract->equipmentReimbursementAmount());
    }

    public function test_monthly_generation_command_rejects_invalid_month_input(): void
    {
        $this->artisan('employee-payslips:generate', ['--month' => '2024-13'])->assertExitCode(2);
    }
}
