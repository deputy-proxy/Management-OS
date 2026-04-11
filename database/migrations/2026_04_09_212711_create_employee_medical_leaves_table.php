<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // New table
        Schema::create('employee_medical_leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('document');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_medical_leaves');
    }
};