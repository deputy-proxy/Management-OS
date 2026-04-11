<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_contracts', function (Blueprint $table) {
            $table->foreignId('vendor_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('vendor_contracts', function (Blueprint $table) {
            $table->dropColumn('vendor_id');
        });
    }
};

