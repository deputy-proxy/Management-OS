<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_definition_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kpi_definition_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 32);
            $table->unsignedSmallInteger('effective_version');
            $table->string('change_summary', 500);
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['kpi_definition_id', 'created_at'], 'kpi_definition_audit_history_idx');
            $table->index(['event', 'created_at'], 'kpi_definition_audit_event_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_definition_audits');
    }
};
