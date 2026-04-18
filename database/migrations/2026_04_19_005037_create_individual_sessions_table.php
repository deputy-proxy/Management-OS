<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // New table
        Schema::create('individual_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('identifier');
            $table->foreignId('customer_id');
            $table->foreignId('course_id');
            $table->foreignId('teacher');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('individual_sessions');
    }
};