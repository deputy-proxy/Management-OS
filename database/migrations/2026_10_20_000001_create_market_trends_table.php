<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_trends', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('category', 100);
            $table->string('metric_key', 100);
            $table->decimal('value', 20, 6);
            $table->string('unit', 50);
            $table->string('market', 100);
            $table->string('segment', 100)->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('source_name', 100);
            $table->text('source_url')->nullable();
            $table->timestamp('observed_at');
            $table->decimal('previous_value', 20, 6)->nullable();
            $table->decimal('change_pct', 12, 4)->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->text('methodology')->nullable();
            $table->json('metadata')->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();

            $table->unique(
                ['metric_key', 'market', 'segment', 'period_start', 'period_end', 'source_name'],
                'market_trends_observation_unique'
            );
            $table->index(
                ['metric_key', 'market', 'segment', 'period_start', 'period_end'],
                'market_trends_dashboard_lookup_index'
            );
            $table->index(['source_name', 'observed_at'], 'market_trends_source_observed_index');
            $table->index(['status', 'observed_at'], 'market_trends_status_observed_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_trends');
    }
};
