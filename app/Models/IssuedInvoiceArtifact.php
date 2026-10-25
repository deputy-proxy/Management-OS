<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class IssuedInvoiceArtifact extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $artifact): void {
            $artifact->retention_category ??= match ($artifact->artifact_type) {
                'invoice_html' => 'rendered_invoice_derivative',
                'ubl_xml' => 'structured_invoice_payload',
                'efactura_response' => 'authority_response',
                'efactura_result_archive' => 'authority_signed_result',
                default => 'unclassified_evidence',
            };
            $artifact->retention_review_status ??= 'pending_review';
            $artifact->legal_hold ??= false;
        });
        static::updating(function (self $artifact): void {
            $immutable = ['issued_invoice_id', 'artifact_type', 'storage_disk', 'storage_key', 'media_type', 'byte_size', 'sha256', 'source_artifact_id'];
            if (array_intersect(array_keys($artifact->getDirty()), $immutable) !== []) {
                throw new RuntimeException('Invoice evidence identity and content metadata are immutable.');
            }
        });

        static::deleting(function (): never {
            throw new RuntimeException('Invoice evidence cannot be deleted through the application model. Retention disposition requires an approved legal schedule and a separate audited process.');
        });
    }

    protected $fillable = [
        'issued_invoice_id', 'artifact_type', 'storage_disk', 'storage_key', 'media_type',
        'byte_size', 'sha256', 'created_by', 'source_artifact_id',
    ];

    protected function casts(): array
    {
        return [
            'byte_size' => 'integer',
            'retention_reviewed_at' => 'datetime',
            'legal_hold' => 'boolean',
            'legal_hold_at' => 'datetime',
        ];
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
