<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

class ProductCostAllocationSnapshot extends Model
{
    public const STATUS_ALLOCATED = 'allocated';

    public const STATUS_UNALLOCATED = 'unallocated';

    public const STATUS_UNKNOWN = 'unknown';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'source_amount_decimal' => 'decimal:2',
            'allocated_amount_decimal' => 'decimal:2',
            'source_snapshot' => 'array',
            'rule_snapshot' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Allocation snapshots are immutable.'));
        static::deleting(fn () => throw new LogicException('Allocation snapshots are immutable.'));
    }

    public function productResource(): BelongsTo
    {
        return $this->belongsTo(ProductResource::class)->withTrashed();
    }

    public function source(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'source_type', 'source_id');
    }

    public function targetable(): MorphTo
    {
        return $this->morphTo();
    }
}
