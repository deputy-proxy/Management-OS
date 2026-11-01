<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kpi_definitions', function (Blueprint $table): void {
            $table->json('filters')->nullable()->after('missing_data');
            $table->json('dimensions')->nullable()->after('filters');
            $table->string('aggregation', 24)->default('count')->after('dimensions');
            $table->json('period')->nullable()->after('aggregation');
        });
    }

    public function down(): void
    {
        Schema::table('kpi_definitions', function (Blueprint $table): void {
            $table->dropColumn(['filters', 'dimensions', 'aggregation', 'period']);
        });
    }
};
