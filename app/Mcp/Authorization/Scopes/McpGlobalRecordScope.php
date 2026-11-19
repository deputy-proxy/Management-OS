<?php

namespace App\Mcp\Authorization\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Explicit scope for tools whose domain permission grants access to the complete domain dataset. */
final class McpGlobalRecordScope implements McpRecordScope
{
    public function apply(Builder $query, User $principal): Builder
    {
        return $query;
    }

    public function allows(User $principal, Model $record): bool
    {
        return true;
    }
}
