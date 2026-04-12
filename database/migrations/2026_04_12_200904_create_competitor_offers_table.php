<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // New table
        Schema::create('competitor_offers', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('competitor_id');
            $table->string('offer_type');
            $table->text('offer_description');
            $table->integer('offer_price');
            $table->string('our_similar_offer');
            $table->text('their_differentiator');
            $table->date('last_check');
            $table->string('status');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_offers');
    }
};