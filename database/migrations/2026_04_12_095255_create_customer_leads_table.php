<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // New table
        Schema::create('customer_leads', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('middle_name');
            $table->string('last_name');
            $table->string('full_name');
            $table->string('email');
            $table->string('phone');
            $table->string('source');
            $table->string('topic_interest');
            $table->string('product_interest');
            $table->string('status');
            $table->dateTime('last_contacted_at');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_leads');
    }
};