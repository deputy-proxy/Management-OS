<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // New table
        Schema::create('employee_pay_slips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id');
            $table->string('month');
            $table->json('items');
            $table->integer('amount');
            $table->date('due_on');
            $table->date('paid_on');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_pay_slips');
    }
};