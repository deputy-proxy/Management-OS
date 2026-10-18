<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_key', 100);
            $table->string('channel', 30)->default('email');
            $table->boolean('enabled')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'event_key', 'channel']);
        });

        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_key', 100);
            $table->string('event_id', 191);
            $table->string('channel', 30)->default('email');
            $table->string('status', 20)->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('payload');
            $table->timestamps();
            $table->unique(['user_id', 'event_key', 'event_id', 'channel'], 'notification_delivery_dedupe');
            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notification_preferences');
    }
};
