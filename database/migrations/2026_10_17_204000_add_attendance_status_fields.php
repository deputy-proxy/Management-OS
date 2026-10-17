<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_attendances', function (Blueprint $table): void {
            $table->string('status')->nullable()->after('student_id');
        });

        Schema::table('individual_sessions', function (Blueprint $table): void {
            $table->string('attendance_status')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('session_attendances', function (Blueprint $table): void {
            $table->dropColumn('status');
        });

        Schema::table('individual_sessions', function (Blueprint $table): void {
            $table->dropColumn('attendance_status');
        });
    }
};
