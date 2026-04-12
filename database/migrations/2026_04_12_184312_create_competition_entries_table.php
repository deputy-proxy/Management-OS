<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // New table
        Schema::create('competition_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('full_name');
            $table->string('email');
            $table->json('content');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_entries');
    }
};