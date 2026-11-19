<?php

namespace App\Mcp\Authorization;

use App\Mcp\Contracts\McpCapabilityRegistrar;
use App\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Mcp\Server\Builder;
use Mcp\Server\Capability\Registry\ReferenceHandler;

abstract class AuthorizedMcpCapabilityRegistrar implements McpCapabilityRegistrar
{
    public function __construct(
        protected readonly McpToolPermissionRegistry $permissions,
        protected readonly McpAuthorizationService $authorization,
        protected readonly McpFieldFilter $fieldFilter,
    ) {}

    /**
     * Register a tool through the mandatory authorization and output-filtering boundary.
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
        $authorization = $this->authorization;
        $fieldFilter = $this->fieldFilter;

        // The SDK's explicit-element loader passes its complete argument bag to closures
        // scoped to ReferenceHandler. This keeps internal session/request values out of
        // tool input while ensuring the authorization check wraps every invocation.
        $wrappedHandler = Closure::bind(
            static function (array $arguments) use ($permission, $authorization, $fieldFilter, $handler): array {
                $principal = Auth::guard('api')->user();
                if (! $principal instanceof User) {
                    throw new AuthorizationException('An authenticated MCP service identity is required.');
                }

                $authorization->authorizeTool($principal, $permission);
                $toolArguments = Arr::except($arguments, ['_session', '_request']);
                $result = $handler($principal, $toolArguments);

                return $fieldFilter->filter($result, $permission->allowedFields);
            },
            null,
            ReferenceHandler::class,
        );

        $builder->addTool(
            handler: $wrappedHandler,
            name: $permission->toolName,
            description: $description,
            inputSchema: $inputSchema,
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
