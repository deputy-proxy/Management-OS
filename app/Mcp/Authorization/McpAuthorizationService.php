<?php

namespace App\Mcp\Authorization;

use App\Mcp\Authorization\Scopes\McpRecordScope;
use App\Models\User;
use Hexters\HexaLite\Models\HexaRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use LogicException;

final class McpAuthorizationService
{
    public function authorizeTool(User $principal, McpToolPermission $permission): void
    {
        $configuredEmail = trim((string) config('mcp.ai_principal_email', ''));
        if ($configuredEmail === '' || ! hash_equals(Str::lower($configuredEmail), Str::lower((string) $principal->email))) {
            throw new AuthorizationException('The MCP service identity is not authorized.');
        }

        if (! $principal->tokenCan('mcp:use') || ! $principal->tokenCan($permission->mcpScope)) {
            throw new AuthorizationException('The MCP tool scope is not authorized.');
        }

        $roles = $this->rolesFor($principal);
        if (! $this->hasDedicatedAssistantRole($roles)) {
            throw new AuthorizationException('The MCP service identity must have exactly one global Assistant role.');
        }

        $ownerRole = trim((string) config('hexa.super_admin_role', 'Owner'));
        if ($ownerRole !== '' && $roles->contains(
            fn ($role): bool => Str::lower(trim((string) $role->name)) === Str::lower($ownerRole)
        )) {
            throw new AuthorizationException('The MCP service identity cannot use the Owner role.');
        }

        foreach ($permission->domainPermissions as $domainPermission) {
            if (! $this->hasDomainPermissionFromRoles($roles, $domainPermission)) {
                throw new AuthorizationException('The required Management-OS domain permission is not granted.');
            }
        }

        $this->recordScope($permission);
    }

    public function hasDomainPermission(User $principal, string $domainPermission): bool
    {
        $roles = $this->rolesFor($principal);
        if (! $this->hasDedicatedAssistantRole($roles)) {
            return false;
        }

        $ownerRole = trim((string) config('hexa.super_admin_role', 'Owner'));
        if ($ownerRole !== '' && $roles->contains(
            fn ($role): bool => Str::lower(trim((string) $role->name)) === Str::lower($ownerRole)
        )) {
            return false;
        }

        return $this->hasDomainPermissionFromRoles($roles, $domainPermission);
    }

    public function scopeQuery(User $principal, McpToolPermission $permission, Builder $query): Builder
    {
        $this->authorizeTool($principal, $permission);

        return $this->recordScope($permission)->apply($query, $principal);
    }

    public function authorizeRecord(User $principal, McpToolPermission $permission, Model $record): void
    {
        $this->authorizeTool($principal, $permission);

        if (! $this->recordScope($permission)->allows($principal, $record)) {
            throw new AuthorizationException('The requested Management-OS record is not accessible.');
        }
    }

    private function rolesFor(User $principal): Collection
    {
        $query = HexaRole::query()
            ->select('hexa_roles.*')
            ->join('hexa_role_user', 'hexa_role_user.role_id', '=', 'hexa_roles.id')
            ->where('hexa_role_user.user_id', $principal->getKey())
            ->where('hexa_roles.guard', (string) config('mcp.domain_permission_guard', config('auth.defaults.guard', 'web')));

        return $query->get();
    }

    private function hasDedicatedAssistantRole(Collection $roles): bool
    {
        if ($roles->count() !== 1) {
            return false;
        }

        $role = $roles->sole();

        return Str::lower(trim((string) $role->name)) === Str::lower((string) config('mcp.principal_role', 'Assistant'))
            && $role->team_id === null;
    }

    private function hasDomainPermissionFromRoles(Collection $roles, string $domainPermission): bool
    {
        $grantedPermissions = $roles
            ->flatMap(fn ($role) => is_array($role->access) ? Arr::flatten($role->access) : [])
            ->filter(fn ($permission): bool => is_string($permission))
            ->values()
            ->all();

        return in_array($domainPermission, $grantedPermissions, true);
    }

    private function recordScope(McpToolPermission $permission): McpRecordScope
    {
        $scopeClass = $permission->recordScope;
        $scope = app($scopeClass);

        if (! $scope instanceof McpRecordScope) {
            throw new LogicException('The MCP tool record scope is not configured correctly.');
        }

        return $scope;
    }
}
