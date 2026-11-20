<?php

namespace App\Services\Mcp;

use App\Models\Mcp\McpExecutionAudit;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Throwable;

final class McpExecutionAuditRecorder
{
    public function __construct(private readonly McpPayloadCanonicalizer $canonicalizer) {}

    /**
     * Start a privacy-safe record before a registered MCP tool is authorized or invoked.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function startToolInvocation(?User $principal, string $toolName, array $arguments): McpExecutionAudit
    {
        $request = request();
        $target = $this->targetFromArguments($arguments);
        $toolVersion = (string) config('mcp.permission_matrix_version', '1.0.0');
        $idempotencyKey = $arguments['idempotency_key'] ?? null;

        return $this->recordEvent('tool_invocation', [
            'ai_principal_id' => $principal?->getKey(),
            'oauth_client_id' => $this->stringAttribute('oauth_client_id', 36),
            'tool_name' => $toolName,
            'tool_version' => $toolVersion,
            'target_type' => $target['type'],
            'target_id' => $target['id'],
            'request_id' => $this->stringAttribute('request_id', 100),
            'idempotency_key' => is_string($idempotencyKey) && preg_match('/^[A-Za-z0-9._:-]{1,128}$/D', $idempotencyKey)
                ? $idempotencyKey
                : null,
            'payload_hash' => $this->canonicalizer->hash(
                $toolName,
                $toolVersion,
                $target['type'],
                $target['id'],
                $arguments,
            ),
            'status' => 'started',
            'started_at' => now(),
            'completed_at' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function completeToolInvocation(McpExecutionAudit $audit, array $result): void
    {
        $reference = $result['domain_event_reference'] ?? null;

        $audit->forceFill([
            'status' => 'succeeded',
            'result_code' => 'success',
            'domain_event_reference' => is_string($reference) ? mb_substr($reference, 0, 191) : null,
            'completed_at' => now(),
        ])->save();
    }

    public function failToolInvocation(McpExecutionAudit $audit, Throwable $exception): void
    {
        $audit->forceFill([
            'status' => 'failed',
            'result_code' => 'failure',
            'error_code' => $this->safeErrorCode($exception),
            'completed_at' => now(),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function recordEvent(string $eventType, array $attributes = []): McpExecutionAudit
    {
        return McpExecutionAudit::query()->create([
            'event_type' => $eventType,
            'status' => 'completed',
            'started_at' => now(),
            'completed_at' => now(),
            ...$attributes,
        ]);
    }

    public function safeErrorCode(Throwable $exception): string
    {
        return match (true) {
            $exception instanceof AuthorizationException => 'authorization_denied',
            $exception instanceof ValidationException => 'validation_failed',
            default => 'tool_execution_failed',
        };
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{type: ?string, id: ?string}
     */
    private function targetFromArguments(array $arguments): array
    {
        foreach ([
            'task_id' => 'task',
            'customer_id' => 'customer',
            'lead_id' => 'customer_lead',
            'project_id' => 'project',
            'record_id' => 'record',
            'id' => 'record',
        ] as $key => $type) {
            $value = $arguments[$key] ?? null;
            if (is_string($value) || is_int($value)) {
                return ['type' => $type, 'id' => mb_substr((string) $value, 0, 191)];
            }
        }

        return ['type' => null, 'id' => null];
    }

    private function stringAttribute(string $key, int $maxLength): ?string
    {
        $value = request()->attributes->get($key);

        return is_string($value) && $value !== '' ? mb_substr($value, 0, $maxLength) : null;
    }
}
