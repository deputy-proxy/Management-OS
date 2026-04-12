<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // New table
        Schema::create('project_phases', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('project_id');
            $table->text('description');
            $table->date('start_on');
            $table->date('end_on');
            $table->integer('order');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_phases');
    }
};