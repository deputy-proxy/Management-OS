<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kpi_definitions', function (Blueprint $table): void {
            $table->boolean('alerts_enabled')->default(false)->after('threshold_direction');
            $table->unsignedInteger('alert_cooldown_minutes')->default(60)->after('alerts_enabled');
            $table->foreignId('alert_owner_id')->nullable()->after('alert_cooldown_minutes')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('kpi_definitions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('alert_owner_id');
            $table->dropColumn(['alerts_enabled', 'alert_cooldown_minutes']);
        });
    }
};
