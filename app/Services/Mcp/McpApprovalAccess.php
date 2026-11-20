<?php

namespace App\Services\Mcp;

use App\Models\User;
use Hexters\HexaLite\Models\HexaRole;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final class McpApprovalAccess
{
    public function canViewApprovals(?User $actor): bool
    {
        return $actor !== null && $this->hasPermission($actor, 'mcp_approval.index');
    }

    public function canReview(?User $actor): bool
    {
        return $actor !== null && $this->hasPermission($actor, 'mcp_approval.approve');
    }

    public function canViewExecutionAudit(?User $actor): bool
    {
        return $actor !== null && $this->hasPermission($actor, 'mcp_execution_audit.index');
    }

    private function hasPermission(User $actor, string $permission): bool
    {
        $configuredPrincipal = trim((string) config('mcp.ai_principal_email', ''));
        if ($configuredPrincipal !== '' && hash_equals(Str::lower($configuredPrincipal), Str::lower((string) $actor->email))) {
            return false;
        }

        $roles = HexaRole::query()
            ->select('hexa_roles.*')
            ->join('hexa_role_user', 'hexa_role_user.role_id', '=', 'hexa_roles.id')
            ->where('hexa_role_user.user_id', $actor->getKey())
            ->where('hexa_roles.guard', (string) config('mcp.domain_permission_guard', config('auth.defaults.guard', 'web')))
            ->whereNull('hexa_roles.team_id')
            ->get();

        if ($roles->isEmpty()) {
            return false;
        }

        $permissions = $roles
            ->flatMap(fn ($role) => Arr::flatten(is_array($role->getAttribute('access')) ? $role->getAttribute('access') : []))
            ->filter(fn ($value): bool => is_string($value))
            ->all();

        return in_array($permission, $permissions, true);
    }
}
