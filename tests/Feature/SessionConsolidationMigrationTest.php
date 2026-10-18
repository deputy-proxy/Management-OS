<?php

namespace Tests\Feature;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SessionConsolidationMigrationTest extends TestCase
{
    public function test_migration_preserves_legacy_rows_relationships_and_polymorphic_references(): void
    {
        $originalConnection = DB::getDefaultConnection();
        config(['database.connections.session_migration_test' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);
        DB::purge('session_migration_test');
        DB::setDefaultConnection('session_migration_test');

        try {
            $this->createLegacySchema();
            DB::table('group_sessions')->insert([
                'id' => 1, 'group_id' => 10, 'course_id' => 20, 'teacher_id' => 30,
                'start_on' => '2026-10-01 09:00:00', 'end_on' => '2026-10-01 10:00:00',
                'status' => 'scheduled', 'identifier' => 'group-session',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('individual_sessions')->insert([
                'id' => 1, 'identifier' => 'individual-session', 'customer_id' => 40,
                'course_id' => 20, 'teacher' => 32, 'teacher_id' => 31,
                'starts_at' => '2026-10-01 11:00:00', 'ends_at' => '2026-10-01 12:00:00',
                'status' => 'scheduled', 'attendance_status' => 'present',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('session_attendances')->insert(['id' => 1, 'group_session_id' => 1, 'student_id' => 50]);
            DB::table('session_assistances')->insert(['id' => 1, 'group_session_id' => 1, 'teacher_id' => 30, 'assistance_date' => '2026-10-01', 'status' => 'complete', 'review' => '[]']);
            DB::table('employee_timesheets')->insert([
                'id' => 1, 'employee_id' => 30, 'date' => '2026-10-01', 'time_start' => '2026-10-01 09:00:00',
                'time_end' => '2026-10-01 10:00:00', 'category' => 'class', 'number_of_hours' => 1,
                'session_id' => 'legacy-reference', 'group_session_id' => 1,
            ]);
            DB::table('activity_log')->insert([
                ['id' => 1, 'subject_type' => 'App\\Models\\GroupSession', 'subject_id' => 1],
                ['id' => 2, 'subject_type' => 'App\\Models\\IndividualSession', 'subject_id' => 1],
            ]);
            DB::table('comments')->insert([
                ['id' => 1, 'commentable_type' => 'App\\Models\\GroupSession', 'commentable_id' => 1],
                ['id' => 2, 'commentable_type' => 'App\\Models\\IndividualSession', 'commentable_id' => 1],
            ]);

            /** @var Migration $migration */
            $migration = require base_path('database/migrations/2026_10_18_000001_unify_sessions_table.php');
            $migration->up();

            $sessions = DB::table('scheduled_sessions')->orderBy('session_type')->get();
            $this->assertCount(2, $sessions);
            $group = DB::table('scheduled_sessions')->where('session_type', 'group')->first();
            $individual = DB::table('scheduled_sessions')->where('session_type', 'individual')->first();
            $this->assertSame(1, (int) $group->id);
            $this->assertSame('group-session', $group->identifier);
            $this->assertSame('individual-session', $individual->identifier);
            $this->assertNotSame(1, (int) $individual->id, 'Overlapping source IDs must be remapped in the unified table.');
            $this->assertSame(31, (int) $individual->teacher_id);
            $this->assertSame(32, (int) $individual->legacy_teacher_id);
            $this->assertSame(1, (int) $individual->legacy_id);
            $this->assertSame(1, (int) DB::table('session_attendances')->value('session_id'));
            $this->assertSame(1, (int) DB::table('session_assistances')->value('session_id'));
            $timesheet = DB::table('employee_timesheets')->first();
            $this->assertSame(1, (int) $timesheet->session_id);
            $this->assertSame('legacy-reference', $timesheet->legacy_session_reference);
            $this->assertSame(1, (int) DB::table('activity_log')->where('subject_type', 'session')->where('subject_id', $group->id)->count());
            $this->assertSame(1, (int) DB::table('activity_log')->where('subject_type', 'session')->where('subject_id', $individual->id)->count());
            $this->assertSame(1, (int) DB::table('comments')->where('commentable_type', 'session')->where('commentable_id', $group->id)->count());
            $this->assertSame(1, (int) DB::table('comments')->where('commentable_type', 'session')->where('commentable_id', $individual->id)->count());
            $this->assertFalse(Schema::hasTable('group_sessions'));
            $this->assertFalse(Schema::hasTable('individual_sessions'));

            $migration->down();

            $this->assertSame('group-session', DB::table('group_sessions')->where('id', 1)->value('identifier'));
            $this->assertSame('individual-session', DB::table('individual_sessions')->where('id', 1)->value('identifier'));
            $this->assertSame(32, (int) DB::table('individual_sessions')->where('id', 1)->value('teacher'));
            $this->assertSame(31, (int) DB::table('individual_sessions')->where('id', 1)->value('teacher_id'));
            $this->assertSame('group_session', DB::table('activity_log')->where('id', 1)->value('subject_type'));
            $this->assertSame(1, (int) DB::table('activity_log')->where('id', 1)->value('subject_id'));
            $this->assertSame('individual_session', DB::table('activity_log')->where('id', 2)->value('subject_type'));
            $this->assertSame(1, (int) DB::table('activity_log')->where('id', 2)->value('subject_id'));
            $this->assertSame('group_session', DB::table('comments')->where('id', 1)->value('commentable_type'));
            $this->assertSame('individual_session', DB::table('comments')->where('id', 2)->value('commentable_type'));
            $this->assertSame(1, (int) DB::table('session_attendances')->value('group_session_id'));
            $this->assertSame('legacy-reference', DB::table('employee_timesheets')->value('session_id'));
        } finally {
            DB::setDefaultConnection($originalConnection);
            DB::purge('session_migration_test');
        }
    }

    private function createLegacySchema(): void
    {
        Schema::create('group_sessions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('group_id');
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('teacher_id');
            $table->dateTime('start_on');
            $table->dateTime('end_on');
            $table->string('status');
            $table->string('identifier')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('individual_sessions', function (Blueprint $table): void {
            $table->id();
            $table->string('identifier');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('teacher');
            $table->unsignedBigInteger('teacher_id')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status');
            $table->string('attendance_status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('session_attendances', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('group_session_id');
            $table->unsignedBigInteger('student_id');
        });
        Schema::create('session_assistances', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('group_session_id');
            $table->unsignedBigInteger('teacher_id');
            $table->date('assistance_date');
            $table->string('status');
            $table->json('review');
        });
        Schema::create('employee_timesheets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('date');
            $table->dateTime('time_start');
            $table->dateTime('time_end');
            $table->string('category');
            $table->integer('number_of_hours');
            $table->string('session_id');
            $table->unsignedBigInteger('group_session_id');
        });
        Schema::create('activity_log', function (Blueprint $table): void {
            $table->id();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
        });
        Schema::create('comments', function (Blueprint $table): void {
            $table->id();
            $table->string('commentable_type')->nullable();
            $table->unsignedBigInteger('commentable_id')->nullable();
        });
        Schema::create('comment_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->string('commentable_type')->nullable();
            $table->unsignedBigInteger('commentable_id')->nullable();
        });
        Schema::create('tasks', function (Blueprint $table): void {
            $table->id();
            $table->string('taskable_type')->nullable();
            $table->unsignedBigInteger('taskable_id')->nullable();
        });
    }
}
