<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // New table
        Schema::create('employee_evaluation_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_description_id');
            $table->json('criteria');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_evaluation_forms');
    }
};