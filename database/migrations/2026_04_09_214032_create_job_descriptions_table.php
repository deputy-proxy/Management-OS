<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // New table
        Schema::create('job_descriptions', function (Blueprint $table) {
            $table->id();
            $table->string('job_title');
            $table->string('job_level');
            $table->text('job_role');
            $table->json('relevant_indicators');
            $table->string('document_code');
            $table->string('document');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_descriptions');
    }
};