<?php

namespace App\Mcp\Authorization\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Standard owner_id scope. Domain-specific scopes should be used for more complex visibility rules. */
final class McpOwnedRecordScope implements McpRecordScope
{
    public function apply(Builder $query, User $principal): Builder
    {
        return $query->where($query->getModel()->qualifyColumn('owner_id'), $principal->getKey());
    }

    public function allows(User $principal, Model $record): bool
    {
        return (string) $record->getAttribute('owner_id') === (string) $principal->getKey();
    }
}
