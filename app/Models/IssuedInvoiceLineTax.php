<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssuedInvoiceLineTax extends Model
{
    protected $fillable = [
        'issued_invoice_line_id', 'tax_scheme_code', 'tax_category_code', 'tax_rate_percent',
        'taxable_base', 'tax_amount', 'exemption_reason_code', 'exemption_reason', 'reverse_charge',
    ];

    protected function casts(): array
    {
        return [
            'tax_rate_percent' => 'decimal:4',
            'taxable_base' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'reverse_charge' => 'boolean',
        ];
    }

    /** @return BelongsTo<IssuedInvoiceLine, $this> */
    public function line(): BelongsTo
    {
        return $this->belongsTo(IssuedInvoiceLine::class, 'issued_invoice_line_id');
    }
}
