<?php

namespace Tests\Feature;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class EmployeePaySlipSchemaMigrationTest extends TestCase
{
    public function test_migration_normalizes_months_and_preserves_existing_amounts_as_manual_baselines(): void
    {
        $this->withIsolatedSchema(function (): void {
            $this->createLegacyPayrollSchema();
            DB::table('employee_pay_slips')->insert([
                'id' => 1,
                'employee_id' => 10,
                'month' => '2026-02-17',
                'items' => '[]',
                'amount' => 123,
                'due_on' => '2026-03-10',
                'paid_on' => '2026-03-10',
                'status' => 'paid',
            ]);

            /** @var Migration $migration */
            $migration = require base_path('database/migrations/2026_10_18_000002_update_employee_pay_slips_for_monthly_workflow.php');
            $migration->up();

            $paySlip = DB::table('employee_pay_slips')->where('id', 1)->first();
            $this->assertSame('2026-02-01', $paySlip->month);
            $this->assertSame('123.00', number_format((float) $paySlip->manual_amount, 2, '.', ''));
            $this->assertSame('123.00', number_format((float) $paySlip->amount, 2, '.', ''));
            $this->assertSame('Paid', $paySlip->status);
            $this->assertTrue(Schema::hasColumn('employee_holidays', 'hours_per_day'));
            $this->assertSame(8, (int) DB::table('employee_holidays')->insertGetId(['start_date' => '2026-02-01', 'end_date' => '2026-02-01', 'status' => 'Approved']) ? DB::table('employee_holidays')->value('hours_per_day') : 0);
        });
    }

    public function test_migration_refuses_to_normalize_existing_duplicate_employee_months(): void
    {
        $this->withIsolatedSchema(function (): void {
            $this->createLegacyPayrollSchema();
            DB::table('employee_pay_slips')->insert([
                ['id' => 1, 'employee_id' => 10, 'month' => '2026-02-01', 'items' => '[]', 'amount' => 100, 'due_on' => '2026-03-01', 'paid_on' => '2026-03-01', 'status' => 'Paid'],
                ['id' => 2, 'employee_id' => 10, 'month' => '2026-02-20', 'items' => '[]', 'amount' => 200, 'due_on' => '2026-03-01', 'paid_on' => '2026-03-01', 'status' => 'Paid'],
            ]);

            /** @var Migration $migration */
            $migration = require base_path('database/migrations/2026_10_18_000002_update_employee_pay_slips_for_monthly_workflow.php');
            try {
                $migration->up();
                $this->fail('Existing duplicate employee/month payslips must be reconciled before migration.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('duplicate payslips', $exception->getMessage());
            }

            $this->assertSame('2026-02-20', DB::table('employee_pay_slips')->where('id', 2)->value('month'));
            $this->assertFalse(Schema::hasColumn('employee_pay_slips', 'manual_amount'));
        });
    }

    private function withIsolatedSchema(\Closure $callback): void
    {
        $originalConnection = DB::getDefaultConnection();
        config(['database.connections.payroll_schema_test' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);
        DB::purge('payroll_schema_test');
        DB::setDefaultConnection('payroll_schema_test');

        try {
            $callback();
        } finally {
            DB::setDefaultConnection($originalConnection);
            DB::purge('payroll_schema_test');
        }
    }

    private function createLegacyPayrollSchema(): void
    {
        Schema::create('employee_pay_slips', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('month');
            $table->json('items');
            $table->integer('amount');
            $table->date('due_on');
            $table->date('paid_on');
            $table->string('status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('employee_contracts', function (Blueprint $table): void {
            $table->id();
            $table->integer('base_salary');
            $table->integer('hourly_rate');
        });
        Schema::create('employee_holidays', function (Blueprint $table): void {
            $table->id();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status');
        });
    }
}
