<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_execution_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_principal_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('human_initiator_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('human_approver_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('approval_id')->nullable()->constrained('mcp_approvals')->restrictOnDelete();
            $table->char('oauth_client_id', 36)->nullable();
            $table->string('event_type', 64);
            $table->string('tool_name', 128)->nullable();
            $table->string('tool_version', 32)->nullable();
            $table->string('target_type', 191)->nullable();
            $table->string('target_id', 191)->nullable();
            $table->string('request_id', 100)->nullable();
            $table->string('idempotency_key', 128)->nullable();
            $table->char('payload_hash', 64)->nullable();
            $table->string('status', 32);
            $table->string('result_code', 64)->nullable();
            $table->string('error_code', 64)->nullable();
            $table->string('domain_event_reference', 191)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['tool_name', 'started_at'], 'mcp_audit_tool_time_idx');
            $table->index(['target_type', 'target_id'], 'mcp_audit_target_idx');
            $table->index('request_id', 'mcp_audit_request_idx');
            $table->index('idempotency_key', 'mcp_audit_idempotency_idx');
            $table->index('oauth_client_id', 'mcp_audit_client_idx');
            $table->index(['status', 'started_at'], 'mcp_audit_status_time_idx');
            $table->index('event_type', 'mcp_audit_event_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_execution_audits');
    }
};
