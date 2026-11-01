<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_ads_import_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('configuration_id')->nullable()->constrained('google_ads_import_configurations')->nullOnDelete();
            $table->string('account_customer_id', 10);
            $table->date('period_start');
            $table->date('period_end');
            $table->string('source_name', 40)->default('google_ads');
            $table->string('source_version', 16);
            $table->string('status', 20)->default('running');
            $table->unsignedInteger('row_count')->default(0);
            $table->string('error_code', 80)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['account_customer_id', 'started_at'], 'ga_import_batches_account_started_idx');
            $table->index(['status', 'started_at'], 'ga_import_batches_status_started_idx');
        });

        Schema::table('google_ads_campaign_daily_metrics', function (Blueprint $table): void {
            $table->foreignId('import_batch_id')->nullable()->after('id');
            $table->foreign('import_batch_id', 'ga_campaign_metrics_batch_fk')
                ->references('id')->on('google_ads_import_batches')->nullOnDelete();
            $table->string('source_name', 40)->default('google_ads')->after('api_version');
            $table->string('source_version', 16)->default('v26')->after('source_name');
        });

        Schema::create('google_ads_campaign_daily_metric_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('metric_id');
            $table->foreignId('import_batch_id');
            $table->foreign('metric_id', 'ga_metric_revisions_metric_fk')
                ->references('id')->on('google_ads_campaign_daily_metrics')->restrictOnDelete();
            $table->foreign('import_batch_id', 'ga_metric_revisions_batch_fk')
                ->references('id')->on('google_ads_import_batches')->restrictOnDelete();
            $table->json('previous_values');
            $table->timestamp('revised_at');
            $table->index(['metric_id', 'revised_at'], 'ga_metric_revisions_metric_revised_idx');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('google_ads_import_batches') && Schema::hasTable('google_ads_campaign_daily_metric_revisions')
            && (DB::table('google_ads_import_batches')->exists()
                || DB::table('google_ads_campaign_daily_metric_revisions')->exists())) {
            throw new RuntimeException('Cannot remove Google Ads import batch or metric revision history once it exists.');
        }

        Schema::dropIfExists('google_ads_campaign_daily_metric_revisions');
        Schema::table('google_ads_campaign_daily_metrics', function (Blueprint $table): void {
            $table->dropForeign('ga_campaign_metrics_batch_fk');
            $table->dropColumn('import_batch_id');
            $table->dropColumn(['source_name', 'source_version']);
        });
        Schema::dropIfExists('google_ads_import_batches');
    }
};
