<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceSubmissionOAuthCredential extends Model
{
    protected $table = 'invoice_submission_oauth_credentials';

    protected $fillable = ['legal_entity_id', 'environment', 'access_token', 'refresh_token', 'expires_at', 'token_refreshed_at'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'datetime',
            'token_refreshed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<LegalEntity, $this> */
    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }
}
