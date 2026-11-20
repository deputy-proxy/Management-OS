<?php

namespace App\Mcp\Authorization;

use App\Mcp\Contracts\McpCapabilityRegistrar;
use App\Models\Mcp\McpApproval;
use App\Models\User;
use App\Services\Mcp\McpApprovalService;
use App\Services\Mcp\McpExecutionAuditRecorder;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use LogicException;
use Mcp\Server\Builder;
use Mcp\Server\Capability\Registry\ReferenceHandler;
use Throwable;

abstract class AuthorizedMcpCapabilityRegistrar implements McpCapabilityRegistrar
{
    public function __construct(
        protected readonly McpToolPermissionRegistry $permissions,
        protected readonly McpAuthorizationService $authorization,
        protected readonly McpFieldFilter $fieldFilter,
        protected readonly McpExecutionAuditRecorder $audit,
    ) {}

    /**
     * Register a read-only tool through the mandatory authorization, audit and output-filtering boundary.
     *
     * @param  callable(User, array<string, mixed>): array<string, mixed>  $handler
     * @param  array<string, mixed>  $inputSchema
     */
    protected function addAuthorizedTool(
        Builder $builder,
        string $toolName,
        callable $handler,
        string $description,
        array $inputSchema,
    ): void {
        $permission = $this->permissions->forTool($toolName);

        if ($permission->operationType !== 'read') {
            throw new LogicException('Write tools cannot use addAuthorizedTool(); use the human-approval request boundary.');
        }

        $builder->addTool(
            handler: $this->wrapAuthorizedHandler($permission, $handler),
            name: $permission->toolName,
            description: $description,
            inputSchema: $inputSchema,
        );
    }

    /**
     * Register the request half of a controlled write. This tool can create an approval
     * request only; it never invokes the domain executor.
     *
     * The preview factory returns ['preview' => [...], 'target_type' => ?string,
     * 'target_id' => ?string]. The caller must provide an opaque idempotency_key argument.
     *
     * @param  callable(User, array<string, mixed>): array<string, mixed>  $previewFactory
     * @param  array<string, mixed>  $inputSchema
     */
    protected function addApprovalRequestTool(
        Builder $builder,
        string $toolName,
        callable $previewFactory,
        string $description,
        array $inputSchema,
    ): void {
        $permission = $this->permissions->forTool($toolName);

        if ($permission->operationType !== 'write' || ! $permission->requiresApproval) {
            throw new LogicException('Approval request tools require an explicit write permission with requires_approval enabled.');
        }

        foreach (['approval_id', 'status', 'expires_at', 'preview_summary'] as $requiredOutputField) {
            if (! array_key_exists($requiredOutputField, $permission->allowedFields)) {
                throw new LogicException("Approval request tools must allowlist the [{$requiredOutputField}] response field.");
            }
        }

        $approvalService = app(McpApprovalService::class);

        $handler = static function (User $principal, array $arguments) use ($previewFactory, $approvalService, $toolName): array {
            $details = $previewFactory($principal, $arguments);
            if (! array_key_exists('preview', $details) || ! is_array($details['preview'])) {
                throw ValidationException::withMessages([
                    'preview' => 'The write tool must provide a safe human-readable approval preview.',
                ]);
            }

            $idempotencyKey = $arguments['idempotency_key'] ?? null;
            if (! is_string($idempotencyKey)) {
                throw ValidationException::withMessages([
                    'idempotency_key' => 'An idempotency_key is required for controlled MCP writes.',
                ]);
            }

            $approval = $approvalService->request(
                $principal,
                $toolName,
                $arguments,
                $details['preview'],
                $idempotencyKey,
                is_string($details['target_type'] ?? null) ? $details['target_type'] : null,
                is_string($details['target_id'] ?? null) || is_int($details['target_id'] ?? null)
                    ? (string) $details['target_id']
                    : null,
            );

            return [
                'approval_id' => $approval->getKey(),
                'status' => $approval->status,
                'expires_at' => $approval->expires_at?->toIso8601String(),
                'preview_summary' => $approval->preview_summary,
            ];
        };

        $builder->addTool(
            handler: $this->wrapAuthorizedHandler($permission, $handler),
            name: $permission->toolName,
            description: $description,
            inputSchema: $inputSchema,
        );
    }

    /**
     * Register the execution half of a controlled write. Its only input is an approval
     * ID; the executor receives the encrypted, validated arguments from the approval
     * record, never new arguments supplied by the MCP caller.
     *
     * @param  callable(User, array<string, mixed>): array<string, mixed>  $executor
     * @param  array<string, mixed>  $inputSchema
     */
    protected function addApprovedExecutionTool(
        Builder $builder,
        string $toolName,
        string $approvedOperationToolName,
        callable $executor,
        string $description,
        array $inputSchema,
    ): void {
        $permission = $this->permissions->forTool($toolName);
        $approvedPermission = $this->permissions->forTool($approvedOperationToolName);

        if ($permission->operationType !== 'write' || ! $permission->requiresApproval
            || $approvedPermission->operationType !== 'write' || ! $approvedPermission->requiresApproval) {
            throw new LogicException('Approved execution tools and their approved operations must both require human approval.');
        }

        $properties = $inputSchema['properties'] ?? null;
        $required = $inputSchema['required'] ?? null;
        if (! is_array($properties)
            || array_keys($properties) !== ['approval_id']
            || ! is_array($required)
            || ! in_array('approval_id', $required, true)) {
            throw new LogicException('Approved execution tools may accept only a required approval_id argument.');
        }

        $approvalService = app(McpApprovalService::class);

        $handler = static function (User $principal, array $arguments) use (
            $approvalService,
            $approvedOperationToolName,
            $executor,
        ): array {
            if (count($arguments) !== 1 || ! array_key_exists('approval_id', $arguments)) {
                throw ValidationException::withMessages([
                    'arguments' => 'Approved execution accepts only the original approval_id.',
                ]);
            }

            $approvalId = filter_var($arguments['approval_id'], FILTER_VALIDATE_INT);
            if (! is_int($approvalId) || $approvalId < 1) {
                throw ValidationException::withMessages([
                    'approval_id' => 'A valid approval_id is required.',
                ]);
            }

            $approval = McpApproval::query()
                ->whereKey($approvalId)
                ->where('tool_name', $approvedOperationToolName)
                ->firstOrFail();

            return $approvalService->executeApproved($approval, $principal, $executor);
        };

        $builder->addTool(
            handler: $this->wrapAuthorizedHandler($permission, $handler),
            name: $permission->toolName,
            description: $description,
            inputSchema: $inputSchema,
        );
    }

    /**
     * @param  callable(User, array<string, mixed>): array<string, mixed>  $handler
     */
    private function wrapAuthorizedHandler(McpToolPermission $permission, callable $handler): Closure
    {
        $authorization = $this->authorization;
        $fieldFilter = $this->fieldFilter;
        $audit = $this->audit;

        // The SDK's explicit-element loader passes its complete argument bag to closures
        // scoped to ReferenceHandler. This keeps internal session/request values out of
        // tool input while ensuring the audit and authorization checks wrap every call.
        return Closure::bind(
            static function (array $arguments) use ($permission, $authorization, $fieldFilter, $audit, $handler): array {
                $principal = Auth::guard('api')->user();
                $normalize = static function (mixed $value) use (&$normalize): mixed {
                    if ($value instanceof \stdClass) {
                        $value = get_object_vars($value);
                    }

                    if (is_array($value)) {
                        foreach ($value as $key => $item) {
                            $value[$key] = $normalize($item);
                        }
                    }

                    return $value;
                };
                $toolArguments = $normalize(Arr::except($arguments, ['_session', '_request']));
                $auditRecord = $audit->startToolInvocation(
                    $principal instanceof User ? $principal : null,
                    $permission->toolName,
                    $toolArguments,
                );

                try {
                    if (! $principal instanceof User) {
                        throw new AuthorizationException('An authenticated MCP service identity is required.');
                    }

                    $authorization->authorizeTool($principal, $permission);
                    $result = $handler($principal, $toolArguments);
                    $filtered = $fieldFilter->filter($result, $permission->allowedFields);
                    $audit->completeToolInvocation($auditRecord, $result);

                    return $filtered;
                } catch (Throwable $exception) {
                    $audit->failToolInvocation($auditRecord, $exception);
                    throw $exception;
                }
            },
            null,
            ReferenceHandler::class,
        );
    }

    /** Apply the declared record scope before search, pagination, totals or aggregation. */
    protected function scopeQuery(User $principal, string $toolName, \Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $this->authorization->scopeQuery($principal, $this->permissions->forTool($toolName), $query);
    }

    /** Re-authorize a record loaded by identifier immediately before returning or acting on it. */
    protected function authorizeRecord(User $principal, string $toolName, Model $record): void
    {
        $this->authorization->authorizeRecord($principal, $this->permissions->forTool($toolName), $record);
    }
}
