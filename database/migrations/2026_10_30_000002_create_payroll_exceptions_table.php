<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_calculation_run_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('employee_pay_slip_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('employee_contract_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->char('exception_key', 64)->unique();
            $table->string('code', 96);
            $table->string('severity', 16);
            $table->string('status', 16)->default('open');
            $table->text('message');
            $table->text('resolution_guidance');
            $table->json('affected_records')->nullable();
            $table->foreignId('detected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('detected_at');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();
            $table->index(['status', 'severity', 'period_start'], 'payroll_exceptions_status_severity_idx');
            $table->index(['employee_id', 'period_start'], 'payroll_exceptions_employee_period_idx');
        });

        Schema::create('payroll_exception_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_exception_id')->constrained('payroll_exceptions')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 32);
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');
            $table->index(['payroll_exception_id', 'created_at'], 'payroll_exception_events_history_idx');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('payroll_exception_events') && DB::table('payroll_exception_events')->exists()) {
            throw new RuntimeException('Payroll exception events are audit records and must be exported before rollback.');
        }
        if (Schema::hasTable('payroll_exceptions') && DB::table('payroll_exceptions')->exists()) {
            throw new RuntimeException('Payroll exceptions are audit records and must be exported before rollback.');
        }

        Schema::dropIfExists('payroll_exception_events');
        Schema::dropIfExists('payroll_exceptions');
    }
};
