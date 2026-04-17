<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->nullableMorphs('taskable');
            $table->index(['taskable_type', 'taskable_id'], 'tasks_taskable_index');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_taskable_index');
            $table->dropMorphs('taskable');
        });
    }
};
