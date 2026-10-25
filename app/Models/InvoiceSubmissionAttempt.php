<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceSubmissionAttempt extends Model
{
    protected $fillable = [
        'invoice_submission_id', 'attempt_number', 'local_attempt_uuid', 'request_started_at',
        'request_completed_at', 'transport_result', 'http_status', 'external_message_id',
        'redacted_error_code', 'redacted_error_message', 'safe_request_metadata',
        'safe_response_metadata', 'response_artifact_id',
    ];

    protected function casts(): array
    {
        return [
            'request_started_at' => 'datetime',
            'request_completed_at' => 'datetime',
            'safe_request_metadata' => 'array',
            'safe_response_metadata' => 'array',
        ];
    }

    /** @return BelongsTo<InvoiceSubmission, $this> */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(InvoiceSubmission::class, 'invoice_submission_id');
    }

    /** @return BelongsTo<IssuedInvoiceArtifact, $this> */
    public function responseArtifact(): BelongsTo
    {
        return $this->belongsTo(IssuedInvoiceArtifact::class, 'response_artifact_id');
    }
}
