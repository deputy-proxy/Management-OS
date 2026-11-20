<?php

namespace App\Services\Mcp;

use App\Mcp\Authorization\McpAuthorizationService;
use App\Mcp\Authorization\McpToolPermission;
use App\Mcp\Authorization\McpToolPermissionRegistry;
use App\Models\Mcp\McpApproval;
use App\Models\Mcp\McpExecutionAudit;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;
use Throwable;

final class McpApprovalService
{
    public function __construct(
        private readonly McpToolPermissionRegistry $permissions,
        private readonly McpAuthorizationService $authorization,
        private readonly McpApprovalAccess $access,
        private readonly McpExecutionAuditRecorder $audit,
        private readonly McpPayloadCanonicalizer $canonicalizer,
    ) {}

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>  $preview
     */
    public function request(
        User $principal,
        string $toolName,
        array $arguments,
        array $preview,
        string $idempotencyKey,
        ?string $targetType = null,
        ?string $targetId = null,
    ): McpApproval {
        $permission = $this->permissions->forTool($toolName);
        $this->assertApprovalRequiredPermission($permission);
        $this->authorization->authorizeTool($principal, $permission);
        $oauthClientId = $this->requiredOAuthClientId();

        if (array_key_exists('idempotency_key', $arguments) && $arguments['idempotency_key'] !== $idempotencyKey) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'The idempotency key must match the value in the validated tool arguments.',
            ]);
        }

        if (! preg_match('/^[A-Za-z0-9._:-]{1,128}$/D', $idempotencyKey)) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'An opaque idempotency key using 1 to 128 ASCII letters, digits, dots, underscores, colons, or hyphens is required.',
            ]);
        }

        if (($targetType !== null && mb_strlen($targetType) > 191) || ($targetId !== null && mb_strlen($targetId) > 191)) {
            throw ValidationException::withMessages([
                'target' => 'Approval target identifiers cannot exceed 191 characters.',
            ]);
        }

        $preview = $this->validatePreview($preview);
        $toolVersion = (string) config('mcp.permission_matrix_version', '1.0.0');
        $payloadHash = $this->canonicalizer->hash(
            $toolName,
            $toolVersion,
            $targetType,
            $targetId,
            $arguments,
            $preview,
        );

        try {
            $encodedArguments = json_encode(
                $arguments,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        } catch (JsonException) {
            throw ValidationException::withMessages([
                'arguments' => 'The approval payload is not valid JSON data.',
            ]);
        }

        if (strlen($encodedArguments) > $this->maxPayloadBytes()) {
            throw ValidationException::withMessages([
                'arguments' => 'The approval payload exceeds the configured size limit.',
            ]);
        }

        $existing = $this->findIdempotentRequest($principal, $idempotencyKey);
        if ($existing !== null) {
            return $this->assertSameIdempotentPayload($existing, $payloadHash, $oauthClientId);
        }

        $request = request();
        $humanInitiatorId = filter_var($request->attributes->get('mcp_human_initiator_id'), FILTER_VALIDATE_INT);
        if (! is_int($humanInitiatorId) || $humanInitiatorId < 1 || ! User::query()->whereKey($humanInitiatorId)->exists()) {
            $humanInitiatorId = null;
        }

        try {
            return DB::transaction(function () use (
                $principal,
                $toolName,
                $toolVersion,
                $targetType,
                $targetId,
                $idempotencyKey,
                $payloadHash,
                $arguments,
                $preview,
                $humanInitiatorId,
                $oauthClientId,
            ): McpApproval {
                $approval = McpApproval::query()->create([
                    'ai_principal_id' => $principal->getKey(),
                    'human_initiator_id' => $humanInitiatorId,
                    'oauth_client_id' => $oauthClientId,
                    'tool_name' => $toolName,
                    'tool_version' => $toolVersion,
                    'target_type' => $targetType,
                    'target_id' => $targetId,
                    'request_id' => $this->requestStringAttribute('request_id', 100),
                    'idempotency_key' => $idempotencyKey,
                    'payload_hash' => $payloadHash,
                    'arguments' => $arguments,
                    'preview' => $preview,
                    'status' => McpApproval::STATUS_PENDING,
                    'expires_at' => now()->addMinutes(min(1440, max(1, (int) config('mcp.approval_ttl_minutes', 15)))),
                ]);

                $this->audit->recordEvent('approval_requested', $this->auditAttributes($approval, [
                    'status' => McpApproval::STATUS_PENDING,
                ]));

                return $approval;
            });
        } catch (QueryException $exception) {
            $existing = $this->findIdempotentRequest($principal, $idempotencyKey);
            if ($existing === null) {
                throw $exception;
            }

            return $this->assertSameIdempotentPayload($existing, $payloadHash, $oauthClientId);
        }
    }

    public function approve(McpApproval $approval, User $actor, string $reason): McpApproval
    {
        return $this->review($approval, $actor, 'approved', $reason);
    }

    public function reject(McpApproval $approval, User $actor, string $reason): McpApproval
    {
        return $this->review($approval, $actor, 'rejected', $reason);
    }

    public function cancel(McpApproval $approval, User $principal, string $reason): McpApproval
    {
        $reason = $this->requiredReason($reason);

        return DB::transaction(function () use ($approval, $principal, $reason): McpApproval {
            $locked = McpApproval::query()->whereKey($approval->getKey())->lockForUpdate()->firstOrFail();

            if ((int) $locked->ai_principal_id !== (int) $principal->getKey()) {
                throw new AuthorizationException('Only the requesting MCP principal may cancel this operation.');
            }
            $this->assertOAuthClientMatches($locked);

            if (! in_array($locked->status, [McpApproval::STATUS_PENDING, McpApproval::STATUS_APPROVED], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Only pending or approved operations can be cancelled.',
                ]);
            }

            $locked->forceFill([
                'status' => McpApproval::STATUS_CANCELLED,
                'cancellation_reason' => $reason,
                'cancelled_at' => now(),
            ])->save();

            $this->audit->recordEvent('approval_cancelled', $this->auditAttributes($locked, [
                'status' => McpApproval::STATUS_CANCELLED,
            ]));

            return $locked->refresh();
        });
    }

    public function expirePending(): int
    {
        $expired = 0;

        McpApproval::query()
            ->whereIn('status', [McpApproval::STATUS_PENDING, McpApproval::STATUS_APPROVED])
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($approvals) use (&$expired): void {
                foreach ($approvals as $approval) {
                    DB::transaction(function () use ($approval, &$expired): void {
                        $locked = McpApproval::query()->whereKey($approval->getKey())->lockForUpdate()->first();
                        if ($locked === null
                            || ! in_array($locked->status, [McpApproval::STATUS_PENDING, McpApproval::STATUS_APPROVED], true)
                            || $locked->expires_at === null
                            || $locked->expires_at->isFuture()) {
                            return;
                        }

                        $locked->forceFill([
                            'status' => McpApproval::STATUS_EXPIRED,
                            'error_code' => 'approval_expired',
                        ])->save();

                        $this->audit->recordEvent('approval_expired', $this->auditAttributes($locked, [
                            'status' => McpApproval::STATUS_EXPIRED,
                            'error_code' => 'approval_expired',
                        ]));

                        $expired++;
                    });
                }
            });

        return $expired;
    }

    public function markStaleExecutionsUncertain(): int
    {
        $marked = 0;
        $staleBefore = now()->subMinutes(max(1, (int) config('mcp.execution_stale_minutes', 5)));

        McpApproval::query()
            ->where('status', McpApproval::STATUS_EXECUTING)
            ->where('updated_at', '<=', $staleBefore)
            ->orderBy('id')
            ->chunkById(100, function ($approvals) use (&$marked, $staleBefore): void {
                foreach ($approvals as $approval) {
                    DB::transaction(function () use ($approval, &$marked, $staleBefore): void {
                        $locked = McpApproval::query()->whereKey($approval->getKey())->lockForUpdate()->first();
                        if ($locked === null
                            || $locked->status !== McpApproval::STATUS_EXECUTING
                            || $locked->updated_at->isAfter($staleBefore)) {
                            return;
                        }

                        $locked->forceFill([
                            'status' => McpApproval::STATUS_EXECUTION_UNCERTAIN,
                            'error_code' => 'execution_interrupted',
                        ])->save();

                        if ($locked->execution_audit_id !== null) {
                            $executionAudit = McpExecutionAudit::query()->whereKey($locked->execution_audit_id)->first();
                            $executionAudit?->forceFill([
                                'status' => 'uncertain',
                                'error_code' => 'execution_interrupted',
                                'completed_at' => now(),
                            ])->save();
                        }

                        $this->audit->recordEvent('execution_interrupted', $this->auditAttributes($locked, [
                            'status' => McpApproval::STATUS_EXECUTION_UNCERTAIN,
                            'error_code' => 'execution_interrupted',
                        ]));

                        $marked++;
                    });
                }
            });

        return $marked;
    }

    /**
     * Execute only a human-approved operation. The callback must call an existing domain action/service.
     *
     * @param  callable(User, array<string, mixed>): array<string, mixed>  $executor
     * @return array<string, mixed>
     */
    public function executeApproved(McpApproval $approval, User $principal, callable $executor): array
    {
        $claim = DB::transaction(function () use ($approval, $principal): array {
            $locked = McpApproval::query()->whereKey($approval->getKey())->lockForUpdate()->firstOrFail();

            if ((int) $locked->ai_principal_id !== (int) $principal->getKey()) {
                throw new AuthorizationException('The MCP principal does not own this approval request.');
            }
            $this->assertOAuthClientMatches($locked);

            $permission = $this->permissions->forTool($locked->tool_name);
            $this->assertApprovalRequiredPermission($permission);
            $this->authorization->authorizeTool($principal, $permission);

            if ($locked->status === McpApproval::STATUS_EXECUTED) {
                return ['state' => 'executed', 'result' => (array) $locked->result];
            }

            if ($locked->status !== McpApproval::STATUS_APPROVED) {
                throw ValidationException::withMessages([
                    'status' => 'The operation is not in an executable approved state.',
                ]);
            }

            if ($locked->expires_at === null || $locked->expires_at->isPast()) {
                $locked->forceFill([
                    'status' => McpApproval::STATUS_EXPIRED,
                    'error_code' => 'approval_expired',
                ])->save();

                $this->audit->recordEvent('approval_expired', $this->auditAttributes($locked, [
                    'status' => McpApproval::STATUS_EXPIRED,
                    'error_code' => 'approval_expired',
                ]));

                return ['state' => 'expired'];
            }

            $this->assertPayloadIntact($locked);

            if ($locked->human_approver_id === null
                || (int) $locked->human_approver_id === (int) $locked->ai_principal_id) {
                throw new AuthorizationException('A distinct authenticated human reviewer is required before execution.');
            }

            if ($locked->human_initiator_id !== null
                && (int) $locked->human_approver_id === (int) $locked->human_initiator_id) {
                throw new AuthorizationException('The human initiator cannot approve their own operation.');
            }

            $locked->forceFill(['status' => McpApproval::STATUS_EXECUTING])->save();

            $execution = $this->audit->recordEvent('approved_operation_execution', $this->auditAttributes($locked, [
                'status' => 'started',
                'human_approver_id' => $locked->human_approver_id,
                'started_at' => now(),
                'completed_at' => null,
            ]));

            $locked->forceFill(['execution_audit_id' => $execution->getKey()])->save();

            return [
                'state' => 'claimed',
                'approval_id' => $locked->getKey(),
                'audit_id' => $execution->getKey(),
                'arguments' => (array) $locked->arguments,
                'tool_name' => $locked->tool_name,
            ];
        });

        if ($claim['state'] === 'executed') {
            return (array) $claim['result'];
        }

        if ($claim['state'] === 'expired') {
            throw ValidationException::withMessages([
                'status' => 'The approval expired before execution.',
            ]);
        }

        /** @var array<string, mixed> $executionArguments */
        $executionArguments = (array) $claim['arguments'];

        try {
            $permission = $this->permissions->forTool((string) $claim['tool_name']);
            $this->assertApprovalRequiredPermission($permission);
            $this->authorization->authorizeTool($principal, $permission);
        } catch (AuthorizationException) {
            $this->finishFailedExecution($claim, true);

            throw ValidationException::withMessages([
                'execution' => 'Current permissions no longer authorize this operation. It was not executed and requires a new approval after access is reviewed.',
            ]);
        }

        try {
            $result = $executor($principal, $executionArguments);
            $encodedResult = json_encode(
                $result,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );

            if (strlen($encodedResult) > $this->maxPayloadBytes()) {
                throw new \LengthException('The MCP execution result exceeds the configured size limit.');
            }
        } catch (Throwable) {
            $this->finishFailedExecution($claim, false);

            throw ValidationException::withMessages([
                'execution' => 'Execution did not return a confirmed outcome. The operation is blocked from automatic retry and requires reconciliation.',
            ]);
        }

        return DB::transaction(function () use ($claim, $result): array {
            $locked = McpApproval::query()->whereKey($claim['approval_id'])->lockForUpdate()->firstOrFail();

            if ($locked->status !== McpApproval::STATUS_EXECUTING) {
                throw ValidationException::withMessages([
                    'execution' => 'The execution outcome requires reconciliation before it can be reported as complete.',
                ]);
            }

            $reference = $result['domain_event_reference'] ?? null;
            $locked->forceFill([
                'status' => McpApproval::STATUS_EXECUTED,
                'result' => $result,
                'result_code' => 'success',
                'executed_at' => now(),
            ])->save();

            $executionAudit = McpExecutionAudit::query()->whereKey($claim['audit_id'])->firstOrFail();
            $executionAudit->forceFill([
                'status' => 'succeeded',
                'result_code' => 'success',
                'domain_event_reference' => is_string($reference) ? mb_substr($reference, 0, 191) : null,
                'completed_at' => now(),
            ])->save();

            return $result;
        });
    }

    private function review(McpApproval $approval, User $actor, string $decision, string $reason): McpApproval
    {
        $reason = $this->requiredReason($reason);

        $outcome = DB::transaction(function () use ($approval, $actor, $decision, $reason): array {
            $locked = McpApproval::query()->whereKey($approval->getKey())->lockForUpdate()->firstOrFail();
            $this->authorizeReviewer($actor);

            if ((int) $locked->ai_principal_id === (int) $actor->getKey()) {
                throw new AuthorizationException('The AI service identity cannot approve or reject its own operation.');
            }

            if ($locked->human_initiator_id !== null
                && (int) $locked->human_initiator_id === (int) $actor->getKey()) {
                throw new AuthorizationException('The human initiator cannot review their own operation.');
            }

            if ($locked->status !== McpApproval::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'status' => 'Only pending MCP operations can be reviewed.',
                ]);
            }

            if ($locked->expires_at === null || $locked->expires_at->isPast()) {
                $locked->forceFill([
                    'status' => McpApproval::STATUS_EXPIRED,
                    'error_code' => 'approval_expired',
                ])->save();

                $this->audit->recordEvent('approval_expired', $this->auditAttributes($locked, [
                    'status' => McpApproval::STATUS_EXPIRED,
                    'error_code' => 'approval_expired',
                ]));

                return ['approval' => $locked, 'expired' => true];
            }

            if ($decision === 'approved') {
                $this->assertPayloadIntact($locked);
                $principal = User::query()->whereKey($locked->ai_principal_id)->firstOrFail();
                $permission = $this->permissions->forTool($locked->tool_name);
                $this->assertApprovalRequiredPermission($permission);
                $this->authorization->authorizePrincipalForTool($principal, $permission);
            }

            $status = $decision === 'approved' ? McpApproval::STATUS_APPROVED : McpApproval::STATUS_REJECTED;
            $locked->forceFill([
                'status' => $status,
                'human_approver_id' => $actor->getKey(),
                'decision_reason' => $reason,
                'reviewed_at' => now(),
            ])->save();

            $this->audit->recordEvent('approval_'.$decision, $this->auditAttributes($locked, [
                'status' => $status,
                'human_approver_id' => $actor->getKey(),
            ]));

            return ['approval' => $locked, 'expired' => false];
        });

        if ($outcome['expired']) {
            throw ValidationException::withMessages([
                'status' => 'The approval expired before it could be reviewed.',
            ]);
        }

        return $outcome['approval']->refresh();
    }

    private function assertPayloadIntact(McpApproval $approval): void
    {
        if (! hash_equals((string) config('mcp.permission_matrix_version', '1.0.0'), $approval->tool_version)) {
            throw ValidationException::withMessages([
                'payload' => 'The MCP permission matrix changed after this operation was requested. Create a new approval request.',
            ]);
        }

        $actual = $this->canonicalizer->hash(
            $approval->tool_name,
            $approval->tool_version,
            $approval->target_type,
            $approval->target_id,
            (array) $approval->arguments,
            (array) $approval->preview,
        );

        if (! hash_equals($approval->payload_hash, $actual)) {
            throw ValidationException::withMessages([
                'payload' => 'The approved operation no longer matches its original validated payload.',
            ]);
        }
    }

    private function assertApprovalRequiredPermission(McpToolPermission $permission): void
    {
        if ($permission->operationType !== 'write' || ! $permission->requiresApproval) {
            throw new \LogicException('Controlled MCP writes must be explicitly marked as approval-required in the permission matrix.');
        }
    }

    private function authorizeReviewer(User $actor): void
    {
        if (! $this->access->canReview($actor)) {
            throw new AuthorizationException('The MCP approval review permission is required.');
        }
    }

    private function requiredReason(string $reason): string
    {
        $reason = trim($reason);

        if ($reason === '' || mb_strlen($reason) > 2000 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $reason)) {
            throw ValidationException::withMessages([
                'reason' => 'A concise review rationale of 1 to 2000 characters is required.',
            ]);
        }

        return $reason;
    }

    /**
     * @param  array<string, mixed>  $preview
     * @return array<string, mixed>
     */
    private function validatePreview(array $preview): array
    {
        if ($preview === [] || count($preview) > 20) {
            throw ValidationException::withMessages([
                'preview' => 'A human-readable approval preview with 1 to 20 fields is required.',
            ]);
        }

        foreach ($preview as $key => $value) {
            $key = (string) $key;
            if (mb_strlen($key) > 64 || preg_match('/password|secret|token|credential|authorization|email|phone|bank|account|address|national|medical/i', $key)) {
                throw ValidationException::withMessages([
                    'preview' => 'The approval preview contains a prohibited or invalid field name.',
                ]);
            }

            if ((! is_scalar($value) && $value !== null) || (is_string($value) && mb_strlen($value) > 500)) {
                throw ValidationException::withMessages([
                    'preview' => 'Approval preview values must be short scalar values.',
                ]);
            }
        }

        return $preview;
    }

    private function findIdempotentRequest(User $principal, string $idempotencyKey): ?McpApproval
    {
        return McpApproval::query()
            ->where('ai_principal_id', $principal->getKey())
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    private function assertSameIdempotentPayload(McpApproval $existing, string $payloadHash, string $oauthClientId): McpApproval
    {
        if (! hash_equals((string) $existing->oauth_client_id, $oauthClientId)) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'This idempotency key belongs to a different OAuth client.',
            ]);
        }

        if (! hash_equals($existing->payload_hash, $payloadHash)) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'This idempotency key was already used for a different MCP operation.',
            ]);
        }

        return $existing;
    }

    /**
     * @param  array<string, mixed>  $claim
     */
    private function finishFailedExecution(array $claim, bool $authorizationBlocked): void
    {
        DB::transaction(function () use ($claim, $authorizationBlocked): void {
            $locked = McpApproval::query()->whereKey($claim['approval_id'])->lockForUpdate()->firstOrFail();

            if ($locked->status === McpApproval::STATUS_EXECUTING) {
                $locked->forceFill([
                    'status' => $authorizationBlocked
                        ? McpApproval::STATUS_EXECUTION_BLOCKED
                        : McpApproval::STATUS_EXECUTION_UNCERTAIN,
                    'error_code' => $authorizationBlocked
                        ? 'authorization_revoked_before_execution'
                        : 'execution_outcome_uncertain',
                ])->save();
            }

            $executionAudit = McpExecutionAudit::query()->whereKey($claim['audit_id'])->firstOrFail();
            $executionAudit->forceFill([
                'status' => $authorizationBlocked ? 'failed' : 'uncertain',
                'error_code' => $authorizationBlocked
                    ? 'authorization_revoked_before_execution'
                    : 'execution_outcome_uncertain',
                'completed_at' => now(),
            ])->save();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function auditAttributes(McpApproval $approval, array $overrides = []): array
    {
        return [
            'ai_principal_id' => $approval->ai_principal_id,
            'human_initiator_id' => $approval->human_initiator_id,
            'human_approver_id' => $approval->human_approver_id,
            'approval_id' => $approval->getKey(),
            'oauth_client_id' => $approval->oauth_client_id,
            'tool_name' => $approval->tool_name,
            'tool_version' => $approval->tool_version,
            'target_type' => $approval->target_type,
            'target_id' => $approval->target_id,
            'request_id' => $approval->request_id,
            'idempotency_key' => $approval->idempotency_key,
            'payload_hash' => $approval->payload_hash,
            ...$overrides,
        ];
    }

    private function requiredOAuthClientId(): string
    {
        $clientId = $this->requestStringAttribute('oauth_client_id', 36);
        if ($clientId === null || ! Str::isUuid($clientId)) {
            throw new AuthorizationException('A validated MCP OAuth client identifier is required for approval requests.');
        }

        return $clientId;
    }

    private function assertOAuthClientMatches(McpApproval $approval): void
    {
        $clientId = $this->requiredOAuthClientId();
        if (! hash_equals((string) $approval->oauth_client_id, $clientId)) {
            throw new AuthorizationException('This approval request belongs to a different MCP OAuth client.');
        }
    }

    private function maxPayloadBytes(): int
    {
        return min(1_048_576, max(1024, (int) config('mcp.approval_max_payload_bytes', 65536)));
    }

    private function requestStringAttribute(string $key, int $maxLength): ?string
    {
        $value = request()->attributes->get($key);

        return is_string($value) && $value !== '' ? mb_substr($value, 0, $maxLength) : null;
    }
}
