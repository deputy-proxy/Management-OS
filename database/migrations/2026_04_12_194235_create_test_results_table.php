<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // New table
        Schema::create('test_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_submission_id');
            $table->foreignId('student_id');
            $table->json('results');
            $table->foreignId('teacher_id');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_results');
    }
};