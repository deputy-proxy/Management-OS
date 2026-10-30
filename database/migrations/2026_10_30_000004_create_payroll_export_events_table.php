<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_export_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_pay_slip_id')->constrained()->restrictOnDelete();
            $table->foreignId('payroll_calculation_run_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('report_type', 48);
            $table->string('format', 8);
            $table->unsignedInteger('row_count');
            $table->char('approved_snapshot_hash', 64);
            $table->char('content_sha256', 64);
            $table->timestamp('created_at');
            $table->index(['employee_pay_slip_id', 'created_at'], 'payroll_export_events_period_idx');
            $table->index(['payroll_calculation_run_id', 'created_at'], 'payroll_export_events_run_idx');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('payroll_export_events') && DB::table('payroll_export_events')->exists()) {
            throw new RuntimeException('Payroll export audit records must be retained or exported before rollback.');
        }

        Schema::dropIfExists('payroll_export_events');
    }
};
