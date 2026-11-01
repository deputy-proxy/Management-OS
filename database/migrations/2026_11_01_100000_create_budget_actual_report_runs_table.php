<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_actual_report_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('budget_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->char('currency_code', 3);
            $table->string('actuals_basis', 64);
            $table->json('dimensions');
            $table->json('report_snapshot');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['budget_version_id', 'created_at'], 'budget_actual_run_version_created_idx');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('budget_actual_report_runs')
            && \Illuminate\Support\Facades\DB::table('budget_actual_report_runs')->exists()) {
            throw new RuntimeException('Cannot roll back budget actual report snapshots while report history exists.');
        }

        Schema::dropIfExists('budget_actual_report_runs');
    }
};