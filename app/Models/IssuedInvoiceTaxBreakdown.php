<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssuedInvoiceTaxBreakdown extends Model
{
    protected $fillable = [
        'issued_invoice_id', 'tax_scheme_code', 'tax_category_code', 'tax_rate_percent',
        'taxable_base', 'tax_amount', 'exemption_reason_code', 'exemption_reason',
    ];

    protected function casts(): array
    {
        return [
            'tax_rate_percent' => 'decimal:4',
            'taxable_base' => 'decimal:2',
            'tax_amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<IssuedInvoice, $this> */
    public function issuedInvoice(): BelongsTo
    {
        return $this->belongsTo(IssuedInvoice::class);
    }
}
