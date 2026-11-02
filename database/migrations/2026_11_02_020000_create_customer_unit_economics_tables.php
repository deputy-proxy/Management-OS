<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_acquisition_cost_entries', function (Blueprint $table): void {
            $table->id();
            $table->date('incurred_on');
            $table->string('source_category', 40)->nullable();
            $table->string('campaign_name')->nullable();
            $table->decimal('amount_decimal', 19, 2);
            $table->char('currency_code', 3);
            $table->string('source_reference', 512);
            $table->string('evidence_reference', 512);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['incurred_on', 'source_category', 'currency_code'], 'cac_entries_period_source_idx');
        });

        Schema::create('customer_retention_observations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->date('observed_on');
            $table->string('state', 20);
            $table->string('policy_version', 40);
            $table->string('source_reference', 512);
            $table->string('evidence_reference', 512);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['customer_id', 'observed_on'], 'customer_retention_customer_date_idx');
        });

        Schema::create('customer_unit_economics_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('run_id')->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('policy_version', 40);
            $table->json('policy_snapshot');
            $table->json('exceptions');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['period_start', 'period_end'], 'cue_runs_period_idx');
        });

        Schema::create('customer_unit_economics_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_unit_economics_run_id')->constrained('customer_unit_economics_runs', 'id', 'cue_results_run_fk')->restrictOnDelete();
            $table->string('source_category', 40);
            $table->char('currency_code', 3);
            $table->unsignedInteger('acquired_customer_count');
            $table->unsignedInteger('cost_entry_count');
            $table->decimal('acquisition_cost_amount', 19, 2)->nullable();
            $table->decimal('cac_amount', 19, 2)->nullable();
            $table->decimal('revenue_amount', 19, 2)->nullable();
            $table->decimal('ltv_amount', 19, 2)->nullable();
            $table->unsignedInteger('revenue_coverage_count');
            $table->unsignedInteger('revenue_unknown_count');
            $table->unsignedInteger('retention_coverage_count');
            $table->unsignedInteger('retention_unknown_count');
            $table->string('result_status', 24);
            $table->json('source_snapshot');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['customer_unit_economics_run_id', 'source_category', 'currency_code'], 'cue_results_run_source_currency_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_unit_economics_results');
        Schema::dropIfExists('customer_unit_economics_runs');
        Schema::dropIfExists('customer_retention_observations');
        Schema::dropIfExists('customer_acquisition_cost_entries');
    }
};
