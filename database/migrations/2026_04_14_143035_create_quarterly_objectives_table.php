<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // New table
        Schema::create('quarterly_objectives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id');
            $table->string('objectives');
            $table->text('description');
            $table->string('quarter');
            $table->string('status');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quarterly_objectives');
    }
};