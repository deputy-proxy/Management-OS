<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_question_pivot', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('test_question_id')
                ->constrained('test_questions')
                ->cascadeOnDelete();
            $table->decimal('points', 5, 2)->default(1.00);
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->unique(['test_id', 'test_question_id']);
            $table->index(['test_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_question');
    }
};