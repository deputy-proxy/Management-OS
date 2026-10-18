<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_group_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('group_id')->constrained('groups')->restrictOnDelete();
            $table->date('started_on');
            $table->date('ended_on')->nullable();
            $table->unsignedBigInteger('active_student_id')->nullable()
                ->virtualAs('CASE WHEN ended_on IS NULL THEN student_id ELSE NULL END');
            $table->timestamps();
            $table->unique(['student_id', 'group_id', 'active_student_id'], 'student_group_memberships_active_unique');
            $table->index(['student_id', 'started_on', 'ended_on'], 'student_group_memberships_student_dates_index');
            $table->index(['group_id', 'started_on', 'ended_on'], 'student_group_memberships_group_dates_index');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('student_group_memberships');
    }
};