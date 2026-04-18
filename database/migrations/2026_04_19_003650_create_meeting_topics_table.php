<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // New table
        Schema::create('meeting_topics', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('meeting_id');
            $table->text('description');
            $table->string('attachment');
            $table->json('outcome');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_topics');
    }
};