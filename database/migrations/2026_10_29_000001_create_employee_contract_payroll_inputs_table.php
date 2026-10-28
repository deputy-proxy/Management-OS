<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_contract_payroll_inputs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_contract_id')->constrained()->restrictOnDelete();
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->string('salary_basis', 24)->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->decimal('scheduled_hours_per_week', 5, 2)->nullable();
            $table->json('work_schedule')->nullable();
            $table->json('allowance_inputs')->nullable();
            $table->string('tax_status_code', 64)->nullable();
            $table->string('source_reference', 255)->nullable();
            $table->string('input_status', 24)->default('unverified');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['employee_contract_id', 'effective_from', 'effective_until'], 'contract_payroll_inputs_effective_idx');
            $table->index(['input_status', 'effective_from'], 'payroll_inputs_status_effective_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_contract_payroll_inputs');
    }
};
