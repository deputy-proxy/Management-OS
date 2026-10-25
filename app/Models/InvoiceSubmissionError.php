<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceSubmissionError extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'invoice_submission_id', 'invoice_submission_attempt_id', 'response_artifact_id',
        'source', 'authority_code', 'severity', 'field_path', 'message_sha256',
        'source_response_sha256', 'retry_classification', 'safe_summary', 'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** @return BelongsTo<InvoiceSubmission, $this> */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(InvoiceSubmission::class, 'invoice_submission_id');
    }

    /** @return BelongsTo<InvoiceSubmissionAttempt, $this> */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(InvoiceSubmissionAttempt::class, 'invoice_submission_attempt_id');
    }

    /** @return BelongsTo<IssuedInvoiceArtifact, $this> */
    public function responseArtifact(): BelongsTo
    {
        return $this->belongsTo(IssuedInvoiceArtifact::class, 'response_artifact_id');
    }
}
