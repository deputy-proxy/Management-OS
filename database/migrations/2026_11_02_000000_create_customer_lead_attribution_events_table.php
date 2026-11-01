<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_lead_attribution_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_lead_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 40);
            $table->string('idempotency_key', 191)->unique();
            $table->json('snapshot');
            $table->timestamp('occurred_at')->index();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['customer_lead_id', 'occurred_at'], 'lead_attr_events_lead_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_lead_attribution_events');
    }
};
