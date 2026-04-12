<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // New table
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('action');
            $table->text('description');
            $table->foreignId('assigned_to');
            $table->foreignId('project_id');
            $table->foreignId('project_phase_id');
            $table->string('status');
            $table->date('due_date');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};