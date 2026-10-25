<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssuedInvoiceParty extends Model
{
    protected $fillable = [
        'issued_invoice_id', 'role', 'legal_name', 'country_code', 'address_line_1',
        'address_line_2', 'locality', 'postal_code', 'region', 'tax_identifier_type',
        'tax_identifier', 'vat_registration_id', 'registration_id', 'email',
    ];

    /** @return BelongsTo<IssuedInvoice, $this> */
    public function issuedInvoice(): BelongsTo
    {
        return $this->belongsTo(IssuedInvoice::class);
    }
}
