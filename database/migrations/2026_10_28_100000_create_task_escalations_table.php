<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_escalations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->date('due_date');
            $table->string('level', 32)->default('overdue');
            $table->string('status', 24)->default('open');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason');
            $table->timestamp('escalated_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['task_id', 'due_date', 'level'], 'task_escalations_task_due_level_unique');
            $table->index(['status', 'owner_id', 'escalated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_escalations');
    }
};
