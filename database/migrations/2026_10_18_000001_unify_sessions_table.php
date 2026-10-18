<?php

use App\Models\GroupSession;
use App\Models\IndividualSession;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('scheduled_sessions')) {
            Schema::create('scheduled_sessions', function (Blueprint $table): void {
            $table->id();
            $table->string('session_type')->index();
            $table->foreignId('group_id')->nullable()->index();
            $table->foreignId('customer_id')->nullable()->index();
            $table->foreignId('course_id')->nullable()->index();
            $table->foreignId('teacher_id')->nullable()->index();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status');
            $table->string('attendance_status')->nullable();
            $table->string('identifier')->nullable();
            // Keep the original key mapping so rollback can reconstruct both source tables.
            $table->string('legacy_type')->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->unsignedBigInteger('legacy_teacher_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
                $table->unique(['legacy_type', 'legacy_id']);
            });
        }

        $groupIds = [];
        foreach (DB::table('group_sessions')->orderBy('id')->get() as $record) {
            DB::table('scheduled_sessions')->updateOrInsert(
                ['legacy_type' => 'group', 'legacy_id' => $record->id],
                [
                    'id' => $record->id,
                    'session_type' => 'group',
                    'group_id' => $record->group_id,
                    'customer_id' => null,
                    'course_id' => $record->course_id,
                    'teacher_id' => $record->teacher_id,
                    'starts_at' => $record->start_on,
                    'ends_at' => $record->end_on,
                    'status' => $record->status,
                    'attendance_status' => null,
                    'identifier' => $record->identifier ?? null,
                    'legacy_teacher_id' => null,
                    'created_at' => $record->created_at,
                    'updated_at' => $record->updated_at,
                    'deleted_at' => $record->deleted_at ?? null,
                ]
            );
            $groupIds[(int) $record->id] = (int) $record->id;
        }

        $individualIds = [];
        foreach (DB::table('individual_sessions')->orderBy('id')->get() as $record) {
            $individualData = [
                'session_type' => 'individual',
                'group_id' => null,
                'customer_id' => $record->customer_id,
                'course_id' => $record->course_id,
                'teacher_id' => $record->teacher_id ?? $record->teacher ?? null,
                'starts_at' => $record->starts_at,
                'ends_at' => $record->ends_at,
                'status' => $record->status,
                'attendance_status' => $record->attendance_status ?? null,
                'identifier' => $record->identifier,
                'legacy_type' => 'individual',
                'legacy_id' => $record->id,
                'legacy_teacher_id' => $record->teacher ?? $record->teacher_id ?? null,
                'created_at' => $record->created_at,
                'updated_at' => $record->updated_at,
                'deleted_at' => $record->deleted_at ?? null,
            ];

            $existing = DB::table('scheduled_sessions')
                ->where('legacy_type', 'individual')
                ->where('legacy_id', $record->id)
                ->first();

            if ($existing) {
                DB::table('scheduled_sessions')->where('id', $existing->id)->update($individualData);
                $newId = (int) $existing->id;
            } else {
                $newId = (int) DB::table('scheduled_sessions')->insertGetId($individualData);
            }
            $individualIds[(int) $record->id] = (int) $newId;
        }

        if (Schema::hasTable('employee_timesheets') && Schema::hasColumn('employee_timesheets', 'session_id')) {
            Schema::table('employee_timesheets', function (Blueprint $table): void {
                $table->renameColumn('session_id', 'legacy_session_reference');
            });
        }

        foreach (['session_attendances', 'session_assistances', 'employee_timesheets'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'group_session_id')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->renameColumn('group_session_id', 'session_id');
                });
            }
        }

        // Preserve polymorphic comments, tasks, activity logs, and subscriptions.
        $this->remapMorphTable('activity_log', 'subject_type', 'subject_id', [
            GroupSession::class => ['App\\Models\\GroupSession', 'group_session'],
            IndividualSession::class => ['App\\Models\\IndividualSession', 'individual_session'],
        ], $groupIds, $individualIds);
        $this->remapMorphTable('comments', 'commentable_type', 'commentable_id', [
            GroupSession::class => ['App\\Models\\GroupSession', 'group_session'],
            IndividualSession::class => ['App\\Models\\IndividualSession', 'individual_session'],
        ], $groupIds, $individualIds);
        $this->remapMorphTable('comment_subscriptions', 'commentable_type', 'commentable_id', [
            GroupSession::class => ['App\\Models\\GroupSession', 'group_session'],
            IndividualSession::class => ['App\\Models\\IndividualSession', 'individual_session'],
        ], $groupIds, $individualIds);
        $this->remapMorphTable('tasks', 'taskable_type', 'taskable_id', [
            GroupSession::class => ['App\\Models\\GroupSession', 'group_session'],
            IndividualSession::class => ['App\\Models\\IndividualSession', 'individual_session'],
        ], $groupIds, $individualIds);

        Schema::dropIfExists('group_sessions');
        Schema::dropIfExists('individual_sessions');
    }

    /** @param array<class-string, array<int, string>> $types @param array<int, int> $groupIds @param array<int, int> $individualIds */
    private function remapMorphTable(string $table, string $typeColumn, string $idColumn, array $types, array $groupIds, array $individualIds): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $typeColumn) || ! Schema::hasColumn($table, $idColumn)) {
            return;
        }

        foreach ($types as $class => $legacyTypes) {
            foreach ($legacyTypes as $legacyType) {
                $mapping = str_contains($class, 'GroupSession') ? $groupIds : $individualIds;
                foreach ($mapping as $oldId => $newId) {
                    DB::table($table)->where($typeColumn, $legacyType)->where($idColumn, $oldId)->update([
                        $typeColumn => 'session',
                        $idColumn => $newId,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::create('group_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_id');
            $table->foreignId('course_id');
            $table->foreignId('teacher_id');
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
            $table->foreignId('customer_id');
            $table->foreignId('course_id');
            $table->foreignId('teacher');
            $table->foreignId('teacher_id')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status');
            $table->string('attendance_status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        foreach (DB::table('scheduled_sessions')->orderBy('id')->get() as $record) {
            $legacyType = $record->legacy_type === 'group' ? 'group_session' : ($record->legacy_type === 'individual' ? 'individual_session' : null);
            if ($legacyType !== null) {
                foreach ([
                    ['activity_log', 'subject_type', 'subject_id'],
                    ['comments', 'commentable_type', 'commentable_id'],
                    ['comment_subscriptions', 'commentable_type', 'commentable_id'],
                    ['tasks', 'taskable_type', 'taskable_id'],
                ] as [$table, $typeColumn, $idColumn]) {
                    if (Schema::hasTable($table) && Schema::hasColumn($table, $typeColumn) && Schema::hasColumn($table, $idColumn)) {
                        DB::table($table)->where($typeColumn, 'session')->where($idColumn, $record->id)->update([
                            $typeColumn => $legacyType,
                            $idColumn => $record->legacy_id,
                        ]);
                    }
                }
            }

            if ($record->legacy_type === 'group') {
                DB::table('group_sessions')->insert([
                    'id' => $record->legacy_id,
                    'group_id' => $record->group_id,
                    'course_id' => $record->course_id,
                    'teacher_id' => $record->teacher_id,
                    'start_on' => $record->starts_at,
                    'end_on' => $record->ends_at,
                    'status' => $record->status,
                    'identifier' => $record->identifier,
                    'created_at' => $record->created_at,
                    'updated_at' => $record->updated_at,
                    'deleted_at' => $record->deleted_at,
                ]);
            } elseif ($record->legacy_type === 'individual') {
                DB::table('individual_sessions')->insert([
                    'id' => $record->legacy_id,
                    'identifier' => $record->identifier,
                    'customer_id' => $record->customer_id,
                    'course_id' => $record->course_id,
                    'teacher' => $record->legacy_teacher_id ?? $record->teacher_id,
                    'teacher_id' => $record->teacher_id,
                    'starts_at' => $record->starts_at,
                    'ends_at' => $record->ends_at,
                    'status' => $record->status,
                    'attendance_status' => $record->attendance_status,
                    'created_at' => $record->created_at,
                    'updated_at' => $record->updated_at,
                    'deleted_at' => $record->deleted_at,
                ]);
            }
        }

        foreach (['session_attendances', 'session_assistances', 'employee_timesheets'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'session_id')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->renameColumn('session_id', 'group_session_id');
                });
            }
        }

        if (Schema::hasTable('employee_timesheets') && Schema::hasColumn('employee_timesheets', 'legacy_session_reference')) {
            Schema::table('employee_timesheets', function (Blueprint $table): void {
                $table->renameColumn('legacy_session_reference', 'session_id');
            });
        }

        Schema::dropIfExists('scheduled_sessions');
    }
};
