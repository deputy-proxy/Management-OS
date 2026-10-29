<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class VendorInvoiceEvent extends Model
{
    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Contractor invoice lifecycle events are immutable.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Contractor invoice lifecycle events cannot be deleted.');
        });
    }

    protected $fillable = ['vendor_invoice_id', 'event_type', 'from_status', 'to_status', 'actor_id', 'reason', 'metadata', 'occurred_at'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'occurred_at' => 'datetime'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(VendorInvoice::class, 'vendor_invoice_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
