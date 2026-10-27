<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competitor_offers', function (Blueprint $table): void {
            $table->string('currency', 3)->nullable();
            $table->string('price_basis', 40)->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamp('observed_at')->nullable();
        });

        Schema::create('competitor_offer_observations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('competitor_offer_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('offer_type');
            $table->text('offer_description');
            $table->decimal('offer_price', 14, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('price_basis', 40)->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamp('observed_at');
            $table->timestamp('recorded_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_status', 24)->default('unknown');
            $table->index(['competitor_offer_id', 'observed_at'], 'offer_obs_offer_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_offer_observations');

        Schema::table('competitor_offers', function (Blueprint $table): void {
            $table->dropColumn(['currency', 'price_basis', 'source_url', 'valid_from', 'valid_until', 'observed_at']);
        });
    }
};
