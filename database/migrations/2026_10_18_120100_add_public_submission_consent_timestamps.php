<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['customer_leads', 'competition_entries', 'newsletter_subscribers', 'product_reviews'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->timestamp('privacy_consented_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['customer_leads', 'competition_entries', 'newsletter_subscribers', 'product_reviews'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('privacy_consented_at');
            });
        }
    }
};
