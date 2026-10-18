<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('session_attendances')
            ->whereNull('deleted_at')
            ->select('session_id', 'student_id')
            ->selectRaw('COUNT(*) AS duplicate_count')
            ->groupBy('session_id', 'student_id')
            ->havingRaw('COUNT(*) > 1')
            ->limit(10)
            ->get();

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException('Cannot enforce one active attendance per session/student until duplicate attendance rows are reconciled. Historical rows were not changed.');
        }

        $orphanSession = DB::table('session_attendances as attendance')
            ->leftJoin('scheduled_sessions as session', 'session.id', '=', 'attendance.session_id')
            ->whereNull('session.id')->value('attendance.id');
        if ($orphanSession !== null) {
            throw new RuntimeException("Cannot add attendance session foreign key: session_attendances row {$orphanSession} references a missing scheduled session.");
        }

        $orphanStudent = DB::table('session_attendances as attendance')
            ->leftJoin('students', 'students.id', '=', 'attendance.student_id')
            ->whereNull('students.id')->value('attendance.id');
        if ($orphanStudent !== null) {
            throw new RuntimeException("Cannot add attendance student foreign key: session_attendances row {$orphanStudent} references a missing student.");
        }

        Schema::table('session_attendances', function (Blueprint $table): void {
            $table->unsignedBigInteger('active_student_id')
                ->nullable()
                ->virtualAs('CASE WHEN deleted_at IS NULL THEN student_id ELSE NULL END');
            $table->unique(['session_id', 'active_student_id'], 'session_attendances_active_session_student_unique');
            $table->foreign('session_id', 'session_attendances_session_id_foreign')
                ->references('id')->on('scheduled_sessions')->restrictOnDelete();
            $table->foreign('student_id', 'session_attendances_student_id_foreign')
                ->references('id')->on('students')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('session_attendances', function (Blueprint $table): void {
            $table->dropForeign(['session_id']);
            $table->dropForeign(['student_id']);
            $table->dropUnique('session_attendances_active_session_student_unique');
            $table->dropColumn('active_student_id');
        });
    }
};
