<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // New table
        Schema::create('Discounts', function (Blueprint $table) {
            $table->id();
            $table->string('applies_to_type');
            $table->integer('applies_to_item');
            $table->integer('value');
            $table->string('type');
            $table->date('start_on');
            $table->date('end_on');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('Discounts');
    }
};