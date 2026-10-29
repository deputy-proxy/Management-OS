<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_calculation_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_contract_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_contract_payroll_input_id')->nullable();
            $table->foreign('employee_contract_payroll_input_id', 'payroll_runs_input_fk')
                ->references('id')->on('employee_contract_payroll_inputs')->restrictOnDelete();
            $table->foreignId('payroll_policy_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('employee_pay_slip_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('idempotency_key', 128)->unique();
            $table->char('request_fingerprint', 64);
            $table->string('run_status', 24)->default('blocked');
            $table->json('blocked_reasons');
            $table->json('input_snapshot');
            $table->json('policy_snapshot')->nullable();
            $table->string('calculation_version', 64)->default('schema-only-v1');
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['employee_id', 'period_start', 'period_end'], 'payroll_runs_employee_period_idx');
            $table->index(['run_status', 'period_start'], 'payroll_runs_status_period_idx');
        });

        Schema::create('payroll_calculation_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_calculation_run_id')->unique()->constrained()->restrictOnDelete();
            $table->string('result_status', 24);
            $table->char('currency_code', 3)->nullable();
            $table->decimal('gross_amount', 19, 2)->nullable();
            $table->decimal('deductions_amount', 19, 2)->nullable();
            $table->decimal('net_amount', 19, 2)->nullable();
            $table->json('result_snapshot');
            $table->char('snapshot_hash', 64);
            $table->timestamp('snapshotted_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('payroll_calculation_results') && DB::table('payroll_calculation_results')->exists()) {
            throw new RuntimeException('Payroll result snapshots are immutable audit records and must be exported before rollback.');
        }
        if (Schema::hasTable('payroll_calculation_runs') && DB::table('payroll_calculation_runs')->exists()) {
            throw new RuntimeException('Payroll calculation runs are immutable audit records and must be exported before rollback.');
        }

        Schema::dropIfExists('payroll_calculation_results');
        Schema::dropIfExists('payroll_calculation_runs');
    }
};
