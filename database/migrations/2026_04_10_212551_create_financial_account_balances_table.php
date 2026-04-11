<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // New table
        Schema::create('financial_account_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_account_id');
            $table->integer('current_balance');
            $table->string('currency');
            $table->date('reported_on');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_account_balances');
    }
};