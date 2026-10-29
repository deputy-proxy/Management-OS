<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_policies', function (Blueprint $table): void {
            $table->json('contract_rules')->nullable()->after('calculation_order');
            $table->json('proration_rule')->nullable()->after('contract_rules');
        });

        Schema::create('payroll_calculation_run_inputs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_calculation_run_id');
            $table->foreign('payroll_calculation_run_id', 'payroll_run_inputs_run_fk')
                ->references('id')->on('payroll_calculation_runs')->restrictOnDelete();
            $table->foreignId('employee_contract_payroll_input_id');
            $table->foreign('employee_contract_payroll_input_id', 'payroll_run_inputs_input_fk')
                ->references('id')->on('employee_contract_payroll_inputs')->restrictOnDelete();
            $table->date('segment_start');
            $table->date('segment_end');
            $table->unsignedSmallInteger('segment_days');
            $table->unsignedSmallInteger('period_days');
            $table->json('input_snapshot');
            $table->timestamps();
            $table->unique(['payroll_calculation_run_id', 'employee_contract_payroll_input_id'], 'payroll_run_inputs_unique');
            $table->index(['employee_contract_payroll_input_id', 'segment_start'], 'payroll_run_inputs_period_idx');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('payroll_calculation_run_inputs') && DB::table('payroll_calculation_run_inputs')->exists()) {
            throw new RuntimeException('Payroll run input-version links are immutable audit records and must be exported before rollback.');
        }
        if (Schema::hasTable('payroll_policies') && DB::table('payroll_policies')->whereNotNull('contract_rules')->orWhereNotNull('proration_rule')->exists()) {
            throw new RuntimeException('Configured contract routes and proration rules must be exported before rollback.');
        }

        Schema::dropIfExists('payroll_calculation_run_inputs');
        Schema::table('payroll_policies', function (Blueprint $table): void {
            $table->dropColumn(['contract_rules', 'proration_rule']);
        });
    }
};
