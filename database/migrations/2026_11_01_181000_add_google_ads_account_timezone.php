<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('google_ads_import_configurations', function (Blueprint $table): void {
            $table->string('account_timezone', 64)->nullable()->after('customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('google_ads_import_configurations', function (Blueprint $table): void {
            $table->dropColumn('account_timezone');
        });
    }
};
