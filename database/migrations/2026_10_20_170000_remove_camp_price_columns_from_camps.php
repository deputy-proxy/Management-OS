<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('camps', function (Blueprint $table) {
            if (Schema::hasColumn('camps', 'camp_price_id')) {
                $table->dropColumn('camp_price_id');
            }
            if (Schema::hasColumn('camps', 'camp_price')) {
                $table->dropColumn('camp_price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('camps', function (Blueprint $table) {
            if (! Schema::hasColumn('camps', 'camp_price')) {
                $table->integer('camp_price')->nullable();
            }
            if (! Schema::hasColumn('camps', 'camp_price_id')) {
                $table->unsignedBigInteger('camp_price_id')->nullable();
            }
        });
    }
};
