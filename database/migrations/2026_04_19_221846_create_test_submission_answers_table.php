<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // New table
        Schema::create('test_submission_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_submission_id');
            $table->foreignId('test_question_id');
            $table->text('answer');
            $table->integer('points');
            $table->foreignId('graded_by');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_submission_answers');
    }
};