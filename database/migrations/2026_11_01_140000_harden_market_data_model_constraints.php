<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Widen the legacy integer price without changing the value of existing rows.
        Schema::table('competitor_offers', function (Blueprint $table): void {
            $table->decimal('offer_price', 14, 2)->change();
            $table->index(['competitor_id', 'status', 'last_check'], 'competitor_offers_status_check_idx');
        });

        Schema::table('competitor_offer_observations', function (Blueprint $table): void {
            // Nullable for pre-existing snapshots; new model-created rows always receive a fingerprint.
            $table->char('observation_key', 64)->nullable()->unique('offer_observation_key_unique');
            $table->index(['source_status', 'observed_at'], 'offer_obs_source_time_idx');
        });

        Schema::table('google_ads_campaign_daily_metrics', function (Blueprint $table): void {
            $table->index(['campaign_id', 'metric_date'], 'gads_metric_campaign_date_idx');
            $table->index(['currency_code', 'metric_date'], 'gads_metric_currency_date_idx');
        });

        Schema::table('market_trends', function (Blueprint $table): void {
            $table->index(['freshness_status', 'observed_at'], 'market_trends_freshness_observed_idx');
            $table->index(['market', 'metric_key', 'period_start', 'period_end'], 'market_trends_market_metric_period_idx');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('competitor_offers') && DB::table('competitor_offers')
            ->whereRaw('offer_price <> ROUND(offer_price, 0)')->exists()) {
            throw new RuntimeException('Fractional competitor offer prices must be migrated before reverting the price column to an integer.');
        }

        Schema::table('market_trends', function (Blueprint $table): void {
            $table->dropIndex('market_trends_freshness_observed_idx');
            $table->dropIndex('market_trends_market_metric_period_idx');
        });

        Schema::table('google_ads_campaign_daily_metrics', function (Blueprint $table): void {
            $table->dropIndex('gads_metric_campaign_date_idx');
            $table->dropIndex('gads_metric_currency_date_idx');
        });

        Schema::table('competitor_offer_observations', function (Blueprint $table): void {
            $table->dropIndex('offer_obs_source_time_idx');
            $table->dropUnique('offer_observation_key_unique');
            $table->dropColumn('observation_key');
        });

        Schema::table('competitor_offers', function (Blueprint $table): void {
            $table->dropIndex('competitor_offers_status_check_idx');
            $table->integer('offer_price')->change();
        });
    }
};
