<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // New table
        Schema::create('session_assistances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_session_id');
            $table->foreignId('teacher_id');
            $table->date('assistance_date');
            $table->string('status');
            $table->json('review');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_assistances');
    }
};