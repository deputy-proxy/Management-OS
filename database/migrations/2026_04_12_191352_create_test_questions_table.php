<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // New table
        Schema::create('test_questions', function (Blueprint $table) {
            $table->id();
            $table->text('question');
            $table->string('question_type');
            $table->text('description');
            $table->text('correct_answer');
            $table->json('data');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_questions');
    }
};