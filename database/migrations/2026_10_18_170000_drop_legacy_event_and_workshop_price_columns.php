<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('events', 'event_price_id')) {
            Schema::table('events', function (Blueprint $table): void {
                $table->dropColumn('event_price_id');
            });
        }

        if (Schema::hasColumn('workshops', 'workshop_price_id')) {
            Schema::table('workshops', function (Blueprint $table): void {
                $table->dropColumn('workshop_price_id');
            });
        }
    }

    public function down(): void
    {
        // Legacy price foreign keys are intentionally retired, not restored.
    }
};
