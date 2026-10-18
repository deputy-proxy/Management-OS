<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_pay_slip_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_pay_slip_id')->constrained()->restrictOnDelete();
            $table->string('item_type')->index();
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->char('period_key', 7);
            $table->string('source_key')->unique();
            $table->date('occurred_on')->index();
            $table->string('description');
            $table->decimal('quantity', 10, 2);
            $table->string('unit', 24);
            $table->decimal('unit_rate', 12, 2);
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('RON');
            $table->json('calculation_snapshot');
            $table->timestamps();
            $table->unique(['employee_pay_slip_id', 'source_type', 'source_id'], 'employee_pay_slip_items_source_unique');
        });
    }

    public function down(): void
    {
        // Financial source snapshots are deliberately retained if a rollback is attempted.
        if (Schema::hasTable('employee_pay_slip_items') && Schema::hasColumn('employee_pay_slip_items', 'id')) {
            if (DB::table('employee_pay_slip_items')->exists()) {
                throw new RuntimeException('Employee payslip source items contain auditable payroll history and must be exported before rollback.');
            }

            Schema::dropIfExists('employee_pay_slip_items');
        }
    }
};
