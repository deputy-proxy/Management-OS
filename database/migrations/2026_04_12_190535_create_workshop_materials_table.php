<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // New table
        Schema::create('workshop_materials', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('workshop_id');
            $table->text('description');
            $table->json('content');
            $table->foreignId('added_by');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_materials');
    }
};