<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // New table
        Schema::create('course_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id');
            $table->integer('amount');
            $table->date('start_on');
            $table->date('end_on');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_prices');
    }
};