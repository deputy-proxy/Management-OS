<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kpi_definition_id')->constrained('kpi_definitions')->restrictOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('dedupe_key', 191);
            $table->string('severity', 16);
            $table->string('status', 20)->default('open');
            $table->decimal('observed_value', 18, 6);
            $table->decimal('trigger_threshold', 18, 6);
            $table->json('evidence');
            $table->string('evidence_url', 2048)->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cooldown_until')->nullable();
            $table->timestamps();
            $table->index(['dedupe_key', 'status', 'created_at'], 'kpi_alert_dedupe_status_idx');
            $table->index(['owner_id', 'status', 'last_seen_at'], 'kpi_alert_owner_status_idx');
        });

        Schema::create('kpi_alert_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kpi_alert_id')->constrained('kpi_alerts')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 40);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['kpi_alert_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_alert_events');
        Schema::dropIfExists('kpi_alerts');
    }
};
