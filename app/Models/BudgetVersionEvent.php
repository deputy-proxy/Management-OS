<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class BudgetVersionEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['budget_version_id', 'event', 'actor_id', 'snapshot', 'created_at'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'created_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Budget audit events are append-only.'));
        static::deleting(fn () => throw new LogicException('Budget audit events are append-only.'));
    }

    /** @return BelongsTo<BudgetVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(BudgetVersion::class, 'budget_version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
