<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // New table
        Schema::create('product_revenues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id');
            $table->foreignId('customer_invoice_id');
            $table->date('month');
            $table->integer('amount');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_revenues');
    }
};