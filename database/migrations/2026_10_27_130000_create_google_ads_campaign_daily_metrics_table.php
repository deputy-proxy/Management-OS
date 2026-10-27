<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_ads_campaign_daily_metrics', function (Blueprint $table): void {
            $table->id();
            $table->string('account_customer_id', 10);
            $table->string('campaign_id', 32);
            $table->string('campaign_name', 255);
            $table->date('metric_date');
            $table->unsignedBigInteger('impressions');
            $table->unsignedBigInteger('clicks');
            $table->unsignedBigInteger('cost_micros');
            $table->decimal('conversions', 18, 6);
            $table->string('currency_code', 3);
            $table->string('api_version', 8);
            $table->timestamp('imported_at');
            $table->unique(['account_customer_id', 'campaign_id', 'metric_date'], 'gads_metric_identity_unique');
            $table->index(['account_customer_id', 'metric_date'], 'gads_metric_account_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_ads_campaign_daily_metrics');
    }
};
