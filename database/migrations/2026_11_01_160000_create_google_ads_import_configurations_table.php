<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_ads_import_configurations', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('customer_id', 10);
            $table->string('login_customer_id', 10)->nullable();
            $table->json('selected_campaign_ids')->nullable();
            $table->unsignedSmallInteger('date_range_days')->default(30);
            $table->date('date_range_start')->nullable();
            $table->date('date_range_end')->nullable();
            $table->json('metric_mapping');
            $table->string('currency_code', 3)->nullable();
            $table->string('credential_reference', 120)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('last_status', 20)->default('not_configured');
            $table->string('last_error_code', 80)->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
            $table->index(['is_active', 'customer_id'], 'gads_config_active_account_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_ads_import_configurations');
    }
};
