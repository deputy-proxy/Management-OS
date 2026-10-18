<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('progress_updates', function (Blueprint $table): void {
            $table->foreignId('session_id')->nullable()->after('student_id')
                ->constrained('scheduled_sessions')->restrictOnDelete();
            $table->index(['session_id', 'student_id'], 'progress_updates_session_student_index');
        });

        Schema::table('test_submissions', function (Blueprint $table): void {
            $table->foreignId('session_id')->nullable()->after('student_id')
                ->constrained('scheduled_sessions')->restrictOnDelete();
            $table->index(['session_id', 'student_id'], 'test_submissions_session_student_index');
        });
    }

    public function down(): void
    {
        Schema::table('test_submissions', function (Blueprint $table): void {
            $table->dropForeign(['session_id']);
            $table->dropIndex('test_submissions_session_student_index');
            $table->dropColumn('session_id');
        });

        Schema::table('progress_updates', function (Blueprint $table): void {
            $table->dropForeign(['session_id']);
            $table->dropIndex('progress_updates_session_student_index');
            $table->dropColumn('session_id');
        });
    }
};
