<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // New table
        Schema::create('progress_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id');
            $table->foreignId('teacher_id');
            $table->text('update');
            $table->date('update_date');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progress_updates');
    }
};