<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_resources', function (Blueprint $table): void {
            $table->string('cost_type', 24)->nullable()->after('name');
        });

        Schema::table('product_resource_allocations', function (Blueprint $table): void {
            $table->string('allocation_method', 32)->default('percentage')->after('allocation_percent');
            $table->date('effective_from')->nullable()->after('month');
            $table->date('effective_until')->nullable()->after('effective_from');
            $table->unsignedInteger('rule_version')->default(1)->after('effective_until');
        });

        DB::table('product_resource_allocations')->update([
            'effective_from' => DB::raw('month'),
        ]);

        Schema::create('profitability_calculation_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('run_id')->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 32);
            $table->string('formula_version', 32);
            $table->json('assumptions');
            $table->json('exceptions');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['period_start', 'period_end'], 'pcr_period_idx');
        });

        Schema::create('profitability_calculation_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('profitability_calculation_run_id');
            $table->unsignedBigInteger('product_id');
            $table->char('currency_code', 3);
            $table->string('result_status', 24);
            $table->decimal('revenue_amount', 19, 2)->nullable();
            $table->decimal('direct_cost_amount', 19, 2)->nullable();
            $table->decimal('overhead_cost_amount', 19, 2)->nullable();
            $table->decimal('provisional_profit_amount', 19, 2)->nullable();
            $table->decimal('provisional_margin_percent', 9, 2)->nullable();
            $table->json('source_snapshot');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['profitability_calculation_run_id', 'product_id'], 'pcrr_run_product_idx');
            $table->index(['product_id', 'currency_code'], 'pcrr_product_currency_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profitability_calculation_results');
        Schema::dropIfExists('profitability_calculation_runs');

        Schema::table('product_resource_allocations', function (Blueprint $table): void {
            $table->dropColumn(['allocation_method', 'effective_from', 'effective_until', 'rule_version']);
        });

        Schema::table('product_resources', function (Blueprint $table): void {
            $table->dropColumn('cost_type');
        });
    }
};
