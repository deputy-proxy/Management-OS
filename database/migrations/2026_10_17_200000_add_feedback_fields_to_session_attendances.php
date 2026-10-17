<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_attendances', function (Blueprint $table): void {
            $table->string('achievement_badge')->nullable()->after('student_id');
            $table->text('feedback')->nullable()->after('achievement_badge');
        });
    }

    public function down(): void
    {
        Schema::table('session_attendances', function (Blueprint $table): void {
            $table->dropColumn(['achievement_badge', 'feedback']);
        });
    }
};
