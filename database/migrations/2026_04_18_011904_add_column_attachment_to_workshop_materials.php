<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshop_materials', function (Blueprint $table) {
            $table->string('attachment')->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('workshop_materials', function (Blueprint $table) {
            $table->dropColumn('attachment');
        });
    }
};

