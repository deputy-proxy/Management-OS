<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // New table
        Schema::create('test_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_id');
            $table->foreignId('student_id');
            $table->json('answers');
            $table->dateTime('submission_timestamp');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_submissions');
    }
};