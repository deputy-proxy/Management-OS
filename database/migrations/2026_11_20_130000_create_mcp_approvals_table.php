<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_principal_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('human_initiator_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('human_approver_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->char('oauth_client_id', 36)->nullable();
            $table->string('tool_name', 128);
            $table->string('tool_version', 32);
            $table->string('target_type', 191)->nullable();
            $table->string('target_id', 191)->nullable();
            $table->string('request_id', 100)->nullable();
            $table->string('idempotency_key', 128);
            $table->char('payload_hash', 64);
            $table->text('arguments');
            $table->text('preview');
            $table->string('status', 32)->default('pending');
            $table->text('decision_reason')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->text('result')->nullable();
            $table->string('result_code', 64)->nullable();
            $table->string('error_code', 64)->nullable();
            // The audit table references approvals. Keep this reverse pointer nullable to avoid a cyclic FK.
            $table->unsignedBigInteger('execution_audit_id')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->unique(['ai_principal_id', 'idempotency_key'], 'mcp_approval_idempotency_unique');
            $table->index(['status', 'expires_at'], 'mcp_approval_expiry_idx');
            $table->index(['target_type', 'target_id'], 'mcp_approval_target_idx');
            $table->index('request_id', 'mcp_approval_request_idx');
            $table->index('oauth_client_id', 'mcp_approval_client_idx');
            $table->index('execution_audit_id', 'mcp_approval_execution_audit_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_approvals');
    }
};
