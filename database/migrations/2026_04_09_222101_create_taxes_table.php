<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // New table
        Schema::create('taxes', function (Blueprint $table) {
            $table->id();
            $table->string('category');
            $table->string('month');
            $table->integer('amount');
            $table->string('status');
            $table->date('due_on');
            $table->date('paid_on');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxes');
    }
};