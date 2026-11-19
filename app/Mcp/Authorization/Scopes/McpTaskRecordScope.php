<?php

namespace App\Mcp\Authorization\Scopes;

use App\Mcp\Authorization\McpAuthorizationService;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class McpTaskRecordScope implements McpRecordScope
{
    public function apply(Builder $query, User $principal): Builder
    {
        if (app(McpAuthorizationService::class)->hasDomainPermission($principal, 'task.view_all')) {
            return $query;
        }

        return $query->where(function (Builder $scope) use ($principal): void {
            $scope->where('assigned_to', $principal->getKey())
                ->orWhere('created_by', $principal->getKey());
        });
    }

    public function allows(User $principal, Model $record): bool
    {
        if (app(McpAuthorizationService::class)->hasDomainPermission($principal, 'task.view_all')) {
            return true;
        }

        return (string) $record->getAttribute('assigned_to') === (string) $principal->getKey()
            || (string) $record->getAttribute('created_by') === (string) $principal->getKey();
    }
}
