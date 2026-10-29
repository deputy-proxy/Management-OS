<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_contract_payroll_inputs', function (Blueprint $table): void {
            $table->decimal('base_amount', 19, 6)->nullable()->after('salary_basis');
        });

        Schema::table('payroll_calculation_results', function (Blueprint $table): void {
            $table->decimal('gross_amount', 19, 6)->nullable()->change();
            $table->decimal('deductions_amount', 19, 6)->nullable()->change();
            $table->decimal('net_amount', 19, 6)->nullable()->change();
        });

        Schema::create('payroll_calculation_result_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_calculation_result_id');
            $table->foreign('payroll_calculation_result_id', 'payroll_result_items_result_fk')
                ->references('id')->on('payroll_calculation_results')->restrictOnDelete();
            $table->unsignedSmallInteger('line_number');
            $table->string('item_code', 128);
            $table->string('item_type', 32);
            $table->string('label', 255);
            $table->string('calculation_method', 48);
            $table->string('source_parameter', 128)->nullable();
            $table->string('base_item_code', 128)->nullable();
            $table->decimal('input_value', 19, 6)->nullable();
            $table->decimal('rate_percent', 9, 6)->nullable();
            $table->decimal('amount', 19, 6);
            $table->char('currency_code', 3);
            $table->string('rounding_mode', 24);
            $table->unsignedTinyInteger('rounding_precision');
            $table->json('calculation_snapshot');
            $table->timestamps();
            $table->unique(['payroll_calculation_result_id', 'line_number'], 'payroll_result_items_line_unique');
            $table->unique(['payroll_calculation_result_id', 'item_code'], 'payroll_result_items_code_unique');
            $table->index(['item_type', 'currency_code'], 'payroll_result_items_type_currency_idx');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('payroll_calculation_result_items') && DB::table('payroll_calculation_result_items')->exists()) {
            throw new RuntimeException('Calculated payroll line items are immutable audit records and must be exported before rollback.');
        }
        if (Schema::hasTable('employee_contract_payroll_inputs') && DB::table('employee_contract_payroll_inputs')->whereNotNull('base_amount')->exists()) {
            throw new RuntimeException('Explicit payroll base amounts must be exported before rolling back the input schema.');
        }
        if (Schema::hasTable('payroll_calculation_results') && DB::table('payroll_calculation_results')
            ->where(function ($query): void {
                $query->whereRaw('gross_amount IS NOT NULL AND gross_amount <> ROUND(gross_amount, 2)')
                    ->orWhereRaw('deductions_amount IS NOT NULL AND deductions_amount <> ROUND(deductions_amount, 2)')
                    ->orWhereRaw('net_amount IS NOT NULL AND net_amount <> ROUND(net_amount, 2)');
            })->exists()) {
            throw new RuntimeException('Payroll totals with more than two decimal places must be exported before rollback.');
        }

        Schema::dropIfExists('payroll_calculation_result_items');
        Schema::table('payroll_calculation_results', function (Blueprint $table): void {
            $table->decimal('gross_amount', 19, 2)->nullable()->change();
            $table->decimal('deductions_amount', 19, 2)->nullable()->change();
            $table->decimal('net_amount', 19, 2)->nullable()->change();
        });
        Schema::table('employee_contract_payroll_inputs', function (Blueprint $table): void {
            $table->dropColumn('base_amount');
        });
    }
};
