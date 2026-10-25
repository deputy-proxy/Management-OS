<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IssuedInvoiceLine extends Model
{
    protected $fillable = [
        'issued_invoice_id', 'line_number', 'description', 'seller_item_code', 'quantity',
        'unit_code', 'unit_price_net', 'discount_amount', 'net_amount',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:6',
            'unit_price_net' => 'decimal:6',
            'discount_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<IssuedInvoice, $this> */
    public function issuedInvoice(): BelongsTo
    {
        return $this->belongsTo(IssuedInvoice::class);
    }

    /** @return HasMany<IssuedInvoiceLineTax, $this> */
    public function taxes(): HasMany
    {
        return $this->hasMany(IssuedInvoiceLineTax::class);
    }
}
