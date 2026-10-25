<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssuedInvoiceArtifact extends Model
{
    protected $fillable = [
        'issued_invoice_id', 'artifact_type', 'storage_disk', 'storage_key', 'media_type',
        'byte_size', 'sha256', 'created_by', 'source_artifact_id',
    ];

    protected function casts(): array
    {
        return ['byte_size' => 'integer'];
    }

    /** @return BelongsTo<IssuedInvoice, $this> */
    public function issuedInvoice(): BelongsTo
    {
        return $this->belongsTo(IssuedInvoice::class);
    }

    /** @return BelongsTo<IssuedInvoiceArtifact, $this> */
    public function sourceArtifact(): BelongsTo
    {
        return $this->belongsTo(IssuedInvoiceArtifact::class, 'source_artifact_id');
    }
}
