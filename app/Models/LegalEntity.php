<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegalEntity extends Model
{
    protected $fillable = [
        'legal_name', 'country_code', 'tax_identifier_type', 'tax_identifier',
        'vat_registration_id', 'registration_id', 'address_line_1', 'address_line_2',
        'locality', 'postal_code', 'region', 'email', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return HasMany<IssuedInvoice, $this> */
    public function issuedInvoices(): HasMany
    {
        return $this->hasMany(IssuedInvoice::class);
    }
}
