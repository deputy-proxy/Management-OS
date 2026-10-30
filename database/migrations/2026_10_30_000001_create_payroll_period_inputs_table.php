<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_period_inputs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_contract_id')->constrained()->restrictOnDelete();
            $table->foreignId('corrects_id')->nullable()->constrained('payroll_period_inputs')->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->string('category', 40);
            $table->string('entry_type', 24);
            $table->string('description', 255);
            $table->decimal('quantity', 19, 6)->nullable();
            $table->string('unit', 32)->nullable();
            $table->decimal('amount', 19, 6)->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->string('source_type', 100)->nullable();
            $table->string('source_reference', 255)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 24)->default('draft');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->index(['employee_contract_id', 'period_start', 'period_end'], 'payroll_period_input_contract_idx');
            $table->index(['status', 'category', 'period_start'], 'payroll_period_input_status_idx');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('payroll_period_inputs') && DB::table('payroll_period_inputs')->exists()) {
            throw new RuntimeException('Payroll period input history must be exported before rollback.');
        }
        Schema::dropIfExists('payroll_period_inputs');
    }
};
