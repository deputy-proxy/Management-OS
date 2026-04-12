<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // New table
        Schema::create('placement_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id');
            $table->date('scheduled_on');
            $table->dateTime('scheduled_at');
            $table->foreignId('teacher_id');
            $table->string('status');
            $table->string('result');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('placement_tests');
    }
};