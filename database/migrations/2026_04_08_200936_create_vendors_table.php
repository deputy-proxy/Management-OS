<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // New table
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('vendor_category_id');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('website');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};