<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // New table
        Schema::create('competitors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('site');
            $table->string('instagram');
            $table->string('facebook');
            $table->string('linkedin');
            $table->string('tiktok');
            $table->json('locations');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitors');
    }
};