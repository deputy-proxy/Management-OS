<?php

namespace App\Mcp\Authorization\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

interface McpRecordScope
{
    /** Apply the principal's record visibility before search, pagination or aggregation. */
    public function apply(Builder $query, User $principal): Builder;

    /** Authorize a record loaded through an untrusted identifier. */
    public function allows(User $principal, Model $record): bool;
}
