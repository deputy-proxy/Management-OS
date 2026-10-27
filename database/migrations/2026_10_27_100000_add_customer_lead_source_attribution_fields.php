<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_leads', function (Blueprint $table): void {
            // Existing free-text source is preserved without inference or rewrite.
            $table->string('source_category', 40)->default('unknown')->index();
            $table->string('campaign_name')->nullable();
            $table->string('referral_partner')->nullable();
            $table->string('attribution_policy_version', 40)->default('v1');
            $table->timestamp('source_captured_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customer_leads', function (Blueprint $table): void {
            $table->dropIndex(['source_category']);
            $table->dropColumn([
                'source_category',
                'campaign_name',
                'referral_partner',
                'attribution_policy_version',
                'source_captured_at',
            ]);
        });
    }
};
