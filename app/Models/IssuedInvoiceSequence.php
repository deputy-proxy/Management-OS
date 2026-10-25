<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssuedInvoiceSequence extends Model
{
    protected $fillable = ['legal_entity_id', 'series', 'policy_version', 'next_number'];

    protected function casts(): array
    {
        return ['next_number' => 'integer'];
    }

    /** @return BelongsTo<LegalEntity, $this> */
    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }
}
