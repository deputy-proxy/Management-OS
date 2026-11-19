<?php

namespace App\Mcp\Authorization;

use App\Mcp\Authorization\Scopes\McpRecordScope;
use InvalidArgumentException;

final class McpToolPermissionRegistry
{
    /** @var array<string, McpToolPermission>|null */
    private ?array $permissions = null;

    public function forTool(string $toolName): McpToolPermission
    {
        $permission = $this->all()[$toolName] ?? null;

        if (! $permission instanceof McpToolPermission) {
            throw new InvalidArgumentException('The MCP tool has no registered permission definition.');
        }

        return $permission;
    }

    /** @return array<string, McpToolPermission> */
    public function all(): array
    {
        if ($this->permissions !== null) {
            return $this->permissions;
        }

        $definitions = config('mcp.tool_permissions', []);
        if (! is_array($definitions)) {
            throw new InvalidArgumentException('The MCP permission matrix must be an array.');
        }

        $permissions = [];
        $scopes = [];
        foreach ($definitions as $toolName => $definition) {
            if (! is_string($toolName) || ! is_array($definition)) {
                throw new InvalidArgumentException('Every MCP permission matrix entry must have a tool name and definition.');
            }

            if (! preg_match('/^[a-zA-Z0-9_.-]{1,128}$/', $toolName)) {
                throw new InvalidArgumentException('MCP tool names must use the supported identifier format.');
            }

            $permission = McpToolPermission::fromArray($toolName, $definition);
            if (! class_exists($permission->recordScope) || ! is_subclass_of($permission->recordScope, McpRecordScope::class)) {
                throw new InvalidArgumentException("The record scope for MCP tool [{$toolName}] is invalid.");
            }

            if (isset($permissions[$toolName])) {
                throw new InvalidArgumentException("Duplicate MCP permission definition for [{$toolName}].");
            }

            if (isset($scopes[$permission->mcpScope])) {
                throw new InvalidArgumentException("MCP scope [{$permission->mcpScope}] must belong to exactly one tool.");
            }

            $permissions[$toolName] = $permission;
            $scopes[$permission->mcpScope] = $toolName;
        }

        return $this->permissions = $permissions;
    }

    /** @return list<string> */
    public function allowedDomainPermissions(): array
    {
        $permissions = [];
        foreach ($this->all() as $definition) {
            array_push($permissions, ...$definition->domainPermissions);
        }

        return array_values(array_unique($permissions));
    }

    /** @return array<string, string> */
    public function passportScopes(): array
    {
        $scopes = [];
        foreach ($this->all() as $permission) {
            $scopes[$permission->mcpScope] = "Invoke the {$permission->toolName} MCP tool.";
        }

        return $scopes;
    }
}
