<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CustomerLeadAttributionEvent extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'customer_lead_id', 'event_type', 'idempotency_key', 'snapshot', 'occurred_at', 'recorded_by',
    ];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'occurred_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Attribution history is append-only.'));
        static::deleting(fn () => throw new LogicException('Attribution history is append-only.'));
    }

    public function customerLead(): BelongsTo
    {
        return $this->belongsTo(CustomerLead::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
