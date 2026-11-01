<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_forecasts', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('cash_forecast_scenarios', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cash_forecast_id')->constrained()->restrictOnDelete();
            $table->string('scenario_name', 120);
            $table->unsignedInteger('version');
            $table->date('period_start');
            $table->date('period_end');
            $table->char('currency_code', 3);
            $table->decimal('opening_balance', 19, 2);
            $table->json('assumptions');
            $table->string('status', 16)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['cash_forecast_id', 'scenario_name', 'version'], 'cash_forecast_scenario_version_unique');
            $table->index(['status', 'period_start', 'period_end'], 'cash_forecast_scenario_period_idx');
        });

        Schema::create('cash_forecast_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cash_forecast_scenario_id')->constrained()->restrictOnDelete();
            $table->string('direction', 8);
            $table->string('description', 200);
            $table->date('expected_on');
            $table->decimal('amount', 19, 2);
            $table->char('currency_code', 3);
            $table->string('confidence', 16);
            $table->string('status', 16)->default('assumed');
            $table->string('source_reference', 160)->nullable();
            $table->timestamps();
            $table->index(['cash_forecast_scenario_id', 'expected_on'], 'cash_forecast_entry_date_idx');
        });

        Schema::create('cash_forecast_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cash_forecast_scenario_id')->constrained()->restrictOnDelete();
            $table->date('as_of');
            $table->json('report_snapshot');
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['cash_forecast_scenario_id', 'as_of'], 'cash_forecast_run_scenario_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_forecast_runs');
        Schema::dropIfExists('cash_forecast_entries');
        Schema::dropIfExists('cash_forecast_scenarios');
        Schema::dropIfExists('cash_forecasts');
    }
};
