<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeePaySlip;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FinanceReconciliationAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_clean_payslip_totals_pass_read_only_audit(): void
    {
        $slip = EmployeePaySlip::factory()->create([
            'manual_amount' => 100.25,
            'amount' => 125.25,
            'status' => 'Open',
            'paid_on' => null,
        ]);

        $this->createLineItem($slip, 25.00);
        DB::table('employee_pay_slips')->where('id', $slip->id)->update(['amount' => 125.25, 'status' => 'Paid', 'paid_on' => '2026-10-18']);

        $this->artisan('finance:reconciliation-audit')
            ->expectsOutputToContain('Read-only finance reconciliation audit. No records will be changed.')
            ->expectsOutputToContain('No configured finance reconciliation findings detected.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('employee_pay_slips', ['id' => $slip->id, 'amount' => '125.25']);
    }

    public function test_audit_reports_total_mismatch_and_paid_slip_without_payment_date_without_mutating_data(): void
    {
        $slip = EmployeePaySlip::factory()->create([
            'manual_amount' => 100,
            'amount' => 999,
            'status' => 'Open',
            'paid_on' => null,
        ]);

        $this->createLineItem($slip, 25);
        DB::table('employee_pay_slips')->where('id', $slip->id)->update(['amount' => 999, 'status' => 'Paid']);

        $this->artisan('finance:reconciliation-audit')
            ->expectsOutputToContain('payslip total mismatch')
            ->expectsOutputToContain('paid payslip missing payment date: 1')
            ->expectsOutputToContain('No data was modified.')
            ->assertExitCode(1);

        $this->assertDatabaseHas('employee_pay_slips', [
            'id' => $slip->id,
            'amount' => '999.00',
            'paid_on' => null,
        ]);
    }

    public function test_audit_reports_duplicate_employee_periods_including_soft_deleted_history(): void
    {
        $employee = Employee::factory()->create();
        $slip = EmployeePaySlip::factory()->create(['employee_id' => $employee->id, 'month' => '2026-10-01']);
        Schema::table('employee_pay_slips', function (Blueprint $table): void {
            $table->dropUnique('employee_pay_slips_employee_month_unique');
        });
        DB::table('employee_pay_slips')->insert([
            'employee_id' => $employee->id,
            'month' => DB::table('employee_pay_slips')->where('id', $slip->id)->value('month'),
            'items' => '[]',
            'amount' => 0,
            'manual_amount' => 0,
            'status' => 'Open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('finance:reconciliation-audit')
            ->expectsOutputToContain('duplicate payslip period')
            ->assertExitCode(1);

        $this->assertDatabaseCount('employee_pay_slips', 2);
    }

    private function createLineItem(EmployeePaySlip $slip, float $amount): void
    {
        DB::table('employee_pay_slip_items')->insert([
            'employee_pay_slip_id' => $slip->id,
            'item_type' => 'session',
            'source_type' => 'session',
            'source_id' => 123,
            'period_key' => $slip->month->format('Y-m'),
            'source_key' => 'test-source-'.$slip->id,
            'occurred_on' => $slip->month->toDateString(),
            'description' => 'Synthetic reconciliation test row',
            'quantity' => 1,
            'unit' => 'hour',
            'unit_rate' => $amount,
            'amount' => $amount,
            'currency' => 'RON',
            'calculation_snapshot' => json_encode(['test' => true], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
