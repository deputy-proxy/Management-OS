<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // New table
        Schema::create('product_resources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('amount');
            $table->date('month');
            $table->morphs('resourceable');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_resources');
    }
};