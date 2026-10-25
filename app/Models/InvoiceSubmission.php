<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceSubmission extends Model
{
    protected $fillable = [
        'issued_invoice_id', 'invoice_snapshot_hash', 'environment', 'technical_spec_version',
        'applicability_decision', 'applicability_reason', 'payload_artifact_id', 'payload_sha256',
        'correlation_uuid', 'external_message_id', 'transmission_state', 'official_processing_state',
        'attempted_at', 'submitted_at', 'responded_at', 'redacted_error_code',
        'redacted_error_message', 'response_artifact_id', 'response_sha256', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'attempted_at' => 'datetime',
            'submitted_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<IssuedInvoice, $this> */
    public function issuedInvoice(): BelongsTo
    {
        return $this->belongsTo(IssuedInvoice::class);
    }

    /** @return HasMany<InvoiceSubmissionAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(InvoiceSubmissionAttempt::class);
    }

    /** @return BelongsTo<IssuedInvoiceArtifact, $this> */
    public function payloadArtifact(): BelongsTo
    {
        return $this->belongsTo(IssuedInvoiceArtifact::class, 'payload_artifact_id');
    }

    /** @return BelongsTo<IssuedInvoiceArtifact, $this> */
    public function responseArtifact(): BelongsTo
    {
        return $this->belongsTo(IssuedInvoiceArtifact::class, 'response_artifact_id');
    }
}
