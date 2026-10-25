<?php

namespace App\Services\Finance;

use App\Models\IssuedInvoice;
use App\Models\IssuedInvoiceArtifact;
use App\Models\IssuedInvoiceEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IssuedInvoiceArtifactAccessService
{
    public function __construct(private readonly IssuedInvoiceSnapshotIntegrityService $integrity) {}

    /**
     * @return array{artifact: IssuedInvoiceArtifact, content: string, filename: string, media_type: string}
     */
    public function read(int $artifactId, User $actor): array
    {
        Gate::forUser($actor)->authorize('customer_invoice.document.download');

        $artifact = IssuedInvoiceArtifact::query()->with([
            'issuedInvoice.legalEntity',
            'issuedInvoice.parties',
            'issuedInvoice.lines.taxes',
            'issuedInvoice.taxBreakdowns',
            'issuedInvoice.events',
            'issuedInvoice.correctedInvoice',
        ])->findOrFail($artifactId);
        $invoice = $artifact->issuedInvoice;

        if (! in_array($invoice->document_status, [IssuedInvoice::STATUS_ISSUED, IssuedInvoice::STATUS_CORRECTED], true)) {
            throw ValidationException::withMessages(['artifact' => 'Only artifacts attached to issued or corrected invoices can be downloaded.']);
        }

        $this->integrity->assertValid($invoice);
        $expectedPrefix = 'issued-invoices/'.$invoice->public_id.'/'.$invoice->issued_snapshot_hash.'/';
        if ($artifact->storage_disk !== 'local'
            || ! str_starts_with($artifact->storage_key, $expectedPrefix)
            || str_contains($artifact->storage_key, '..')
            || strlen($artifact->storage_key) > 1024) {
            throw ValidationException::withMessages(['artifact' => 'The artifact is not stored under the approved private invoice storage prefix.']);
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($artifact->storage_key)) {
            throw ValidationException::withMessages(['artifact' => 'The private artifact is missing. Restore it from backup before retrying.']);
        }
        $content = $disk->get($artifact->storage_key);
        if (! is_string($content) || strlen($content) > 20 * 1024 * 1024
            || strlen($content) !== (int) $artifact->byte_size
            || ! hash_equals($artifact->sha256, hash('sha256', $content))) {
            throw ValidationException::withMessages(['artifact' => 'The private artifact failed its size or SHA-256 integrity check.']);
        }

        $mediaType = $this->safeMediaType((string) $artifact->media_type);
        $extension = match ($artifact->artifact_type) {
            'invoice_html' => 'html',
            'ubl_xml' => 'xml',
            'efactura_result_archive' => 'zip',
            'efactura_response' => str_contains($mediaType, 'json') ? 'json' : 'xml',
            default => 'bin',
        };
        $publicId = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $invoice->public_id) ?: 'invoice';
        $artifactType = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $artifact->artifact_type) ?: 'artifact';
        $filename = sprintf('%s-%s-%s.%s', $publicId, $artifactType, substr($artifact->sha256, 0, 12), $extension);

        DB::transaction(function () use ($invoice, $artifact, $actor, $mediaType): void {
            IssuedInvoiceEvent::query()->create([
                'issued_invoice_id' => $invoice->getKey(),
                'event_type' => 'invoice_artifact_downloaded',
                'actor_id' => $actor->getKey(),
                'correlation_uuid' => (string) Str::uuid(),
                'safe_metadata' => [
                    'artifact_id' => $artifact->getKey(),
                    'artifact_type' => $artifact->artifact_type,
                    'sha256' => $artifact->sha256,
                    'byte_size' => $artifact->byte_size,
                    'media_type' => $mediaType,
                ],
                'created_at' => now(),
            ]);
        });

        return ['artifact' => $artifact, 'content' => $content, 'filename' => $filename, 'media_type' => $mediaType];
    }

    private function safeMediaType(string $mediaType): string
    {
        $allowed = [
            'text/html; charset=UTF-8',
            'application/xml',
            'application/xml; charset=UTF-8',
            'application/json',
            'application/zip',
            'application/octet-stream',
        ];

        return in_array($mediaType, $allowed, true) ? $mediaType : 'application/octet-stream';
    }
}
