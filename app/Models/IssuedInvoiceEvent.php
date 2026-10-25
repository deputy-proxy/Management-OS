<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssuedInvoiceEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'issued_invoice_id', 'event_type', 'from_status', 'to_status', 'actor_id',
        'reason', 'correlation_uuid', 'safe_metadata', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'safe_metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<IssuedInvoice, $this> */
    public function issuedInvoice(): BelongsTo
    {
        return $this->belongsTo(IssuedInvoice::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
