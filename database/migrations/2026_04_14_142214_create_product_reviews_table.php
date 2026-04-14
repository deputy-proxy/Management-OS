<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // New table
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('product_type');
            $table->integer('product_id');
            $table->string('reviewer_name');
            $table->string('reviewer_email');
            $table->foreignId('customer_id');
            $table->text('content');
            $table->integer('rating');
            $table->dateTime('reviewed_on');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
    }
};