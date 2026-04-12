<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_timesheets', function (Blueprint $table) {
            $table->foreignId('group_session_id')->after('number_of_hours');
        });
    }

    public function down(): void
    {
        Schema::table('employee_timesheets', function (Blueprint $table) {
            $table->dropColumn('group_session_id');
        });
    }
};

