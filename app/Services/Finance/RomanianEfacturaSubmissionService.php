<?php

namespace App\Services\Finance;

use App\Models\InvoiceSubmission;
use App\Models\InvoiceSubmissionAttempt;
use App\Models\IssuedInvoice;
use App\Models\IssuedInvoiceArtifact;
use App\Models\IssuedInvoiceEvent;
use App\Models\IssuedInvoiceParty;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class RomanianEfacturaSubmissionService
{
    public function __construct(
        private readonly AnafOAuthTokenProvider $tokens,
        private readonly IssuedInvoiceSnapshotIntegrityService $integrity,
        private readonly RomanianEfacturaUblValidator $validator,
    ) {}

    public function submit(int $invoiceId, string $environment, User $actor, string $applicabilityDecision = 'required', string $applicabilityReason = 'Explicitly authorized submission request.'): InvoiceSubmission
    {
        Gate::forUser($actor)->authorize('customer_invoice.submit');
        $this->assertEnvironment($environment);
        if (! in_array($applicabilityDecision, ['required', 'not_required', 'review_required'], true) || blank($applicabilityReason)) {
            throw ValidationException::withMessages(['applicability' => 'An approved applicability decision and reason are required.']);
        }

        [$invoice, $submission, $attempt, $xml, $cif, $standard] = DB::transaction(function () use ($invoiceId, $environment, $actor, $applicabilityDecision, $applicabilityReason): array {
            $invoice = IssuedInvoice::query()->lockForUpdate()->with(['legalEntity', 'parties', 'lines.taxes', 'taxBreakdowns', 'events', 'correctedInvoice', 'artifacts'])->findOrFail($invoiceId);
            if (! in_array($invoice->document_status, [IssuedInvoice::STATUS_ISSUED, IssuedInvoice::STATUS_CORRECTED], true)) {
                throw ValidationException::withMessages(['document_status' => 'Only issued or corrected invoices can be submitted.']);
            }
            $this->integrity->assertValid($invoice);
            $hash = (string) $invoice->issued_snapshot_hash;
            $submission = $invoice->submissions()->where('invoice_snapshot_hash', $hash)->where('environment', $environment)->lockForUpdate()->first();

            if ($applicabilityDecision !== 'required') {
                if ($submission !== null) {
                    return [$invoice, $submission, null, null, null, null];
                }
                $submission = $invoice->submissions()->create([
                    'invoice_snapshot_hash' => $hash, 'environment' => $environment,
                    'technical_spec_version' => RomanianEfacturaUblValidator::CIUS_VERSION,
                    'applicability_decision' => $applicabilityDecision, 'applicability_reason' => $applicabilityReason,
                    'correlation_uuid' => (string) str()->uuid(), 'transmission_state' => $applicabilityDecision === 'not_required' ? 'not_required' : 'blocked',
                    'created_by' => $actor->getKey(),
                ]);
                $this->event($invoice, $actor, $submission, 'efactura_submission_skipped', ['environment' => $environment, 'decision' => $applicabilityDecision]);

                return [$invoice, $submission, null, null, null, null];
            }

            if ($submission !== null) {
                $last = $submission->attempts()->orderByDesc('attempt_number')->first();
                if ($submission->transmission_state === 'failed' && $last?->transport_result === 'auth_failed') {
                    // Safe retry: authorization failed before any upload request was sent.
                } elseif ($submission->transmission_state === 'submitting') {
                    throw ValidationException::withMessages(['submission' => 'A submission attempt is already in progress.']);
                } elseif ($submission->transmission_state === 'unknown') {
                    throw ValidationException::withMessages(['submission' => 'The prior upload outcome is uncertain. Reconcile with ANAF before retrying.']);
                } elseif ($submission->transmission_state === 'blocked' && $submission->applicability_decision === 'review_required') {
                    $submission->forceFill([
                        'applicability_decision' => 'required',
                        'applicability_reason' => $applicabilityReason,
                        'transmission_state' => 'submitting',
                        'redacted_error_code' => null,
                        'redacted_error_message' => null,
                    ])->save();
                    $this->event($invoice, $actor, $submission, 'efactura_applicability_approved', ['environment' => $environment, 'decision' => 'required']);
                } else {
                    return [$invoice, $submission, null, null, null, null];
                }
            }

            $artifact = $invoice->artifacts->where('artifact_type', 'ubl_xml')->sortByDesc('id')->first();
            if (! $artifact instanceof IssuedInvoiceArtifact) {
                throw ValidationException::withMessages(['payload_artifact' => 'Generate validated UBL XML before submission.']);
            }
            $disk = Storage::disk($artifact->storage_disk);
            if (! $disk->exists($artifact->storage_key)) {
                throw ValidationException::withMessages(['payload_artifact' => 'The private UBL XML artifact is missing.']);
            }
            $xml = $disk->get($artifact->storage_key);
            if (! hash_equals($artifact->sha256, hash('sha256', $xml)) || strlen($xml) !== (int) $artifact->byte_size) {
                throw ValidationException::withMessages(['payload_artifact' => 'The UBL XML artifact failed its integrity check.']);
            }
            $this->validator->validate($xml);
            $seller = $invoice->parties->firstWhere('role', 'seller');
            if (! $seller instanceof IssuedInvoiceParty || strtoupper((string) $seller->country_code) !== 'RO') {
                throw ValidationException::withMessages(['seller' => 'A Romanian seller is required.']);
            }
            $cif = preg_replace('/^RO/i', '', trim((string) $seller->tax_identifier));
            if (! is_string($cif) || ! preg_match('/^\d{2,10}$/', $cif)) {
                throw ValidationException::withMessages(['seller.tax_identifier' => 'A numeric Romanian CIF is required for the upload request.']);
            }
            if (! in_array($invoice->document_type, [IssuedInvoice::TYPE_INVOICE, IssuedInvoice::TYPE_CREDIT_NOTE], true)) {
                throw ValidationException::withMessages(['document_type' => 'Only invoices and credit notes can be submitted.']);
            }
            $standard = $invoice->document_type === IssuedInvoice::TYPE_CREDIT_NOTE ? 'CN' : 'UBL';

            $submission ??= $invoice->submissions()->create([
                'invoice_snapshot_hash' => $hash, 'environment' => $environment,
                'technical_spec_version' => RomanianEfacturaUblValidator::CIUS_VERSION,
                'applicability_decision' => 'required', 'applicability_reason' => $applicabilityReason,
                'payload_artifact_id' => $artifact->getKey(), 'payload_sha256' => $artifact->sha256,
                'correlation_uuid' => (string) str()->uuid(), 'transmission_state' => 'submitting',
                'attempted_at' => now(), 'created_by' => $actor->getKey(),
            ]);
            $submission->forceFill(['transmission_state' => 'submitting', 'attempted_at' => now()])->save();
            $attempt = $submission->attempts()->create([
                'attempt_number' => ((int) $submission->attempts()->max('attempt_number')) + 1,
                'local_attempt_uuid' => (string) str()->uuid(), 'request_started_at' => now(), 'transport_result' => 'in_progress',
                'safe_request_metadata' => ['endpoint' => 'upload', 'standard' => $standard, 'content_type' => 'text/plain', 'payload_sha256' => $artifact->sha256],
            ]);

            return [$invoice, $submission, $attempt, $xml, $cif, $standard];
        });

        if (! $attempt instanceof InvoiceSubmissionAttempt || ! is_string($xml) || ! is_string($cif) || ! is_string($standard)) {
            return $submission;
        }
        try {
            $token = $this->tokens->accessToken($invoice->legalEntity, $environment);
        } catch (Throwable) {
            $this->finish($submission, $attempt, 'auth_failed', null, null, 'oauth_unavailable', 'ANAF authorization failed before upload.');
            throw ValidationException::withMessages(['authorization' => 'ANAF authorization failed before upload. Check environment secrets and retry after correcting them.']);
        }

        $settings = config('services.efactura.environments.'.$environment);
        $url = rtrim((string) ($settings['upload_url'] ?? ''), '/');
        if (! $this->approvedEndpoint($url, $environment, 'upload')) {
            $this->finish($submission, $attempt, 'auth_failed', null, null, 'endpoint_configuration_invalid', 'The configured ANAF endpoint is not approved.');
            throw ValidationException::withMessages(['endpoint' => 'Upload URL does not match the approved ANAF environment.']);
        }
        try {
            $response = Http::withToken($token)
                ->connectTimeout((int) config('services.efactura.connect_timeout_seconds', 5))
                ->timeout((int) config('services.efactura.timeout_seconds', 30))->withBody($xml, 'text/plain')
                ->post($url.'?'.http_build_query(['standard' => $standard, 'cif' => $cif]));
        } catch (ConnectionException) {
            $this->finish($submission, $attempt, 'transport_unknown', null, null, 'transport_outcome_unknown', 'Upload outcome is uncertain; reconcile with ANAF before retry.');
            throw ValidationException::withMessages(['submission' => 'Connection ended without a definitive response. Submission is marked unknown; reconcile before retrying.']);
        } catch (Throwable) {
            $this->finish($submission, $attempt, 'transport_unknown', null, null, 'transport_outcome_unknown', 'Upload outcome is uncertain; reconcile with ANAF before retry.');
            throw ValidationException::withMessages(['submission' => 'Upload outcome is uncertain. Reconcile with ANAF before retrying.']);
        }

        $artifact = $this->storeResponse($invoice, $submission, $attempt, $actor, $response);
        $parsed = $this->parseUpload($response);
        if ($response->successful() && $parsed['execution_status'] === 0 && $parsed['external_id'] !== null) {
            $this->finish($submission, $attempt, 'accepted_for_processing', $response->status(), $parsed['external_id'], null, null, $artifact);
        } elseif ($response->successful() && $parsed['execution_status'] === 1) {
            $this->finish($submission, $attempt, 'anaf_rejected', $response->status(), null, 'anaf_upload_rejected', 'ANAF rejected the upload; inspect the private response artifact.', $artifact);
        } elseif ($response->status() >= 500 || in_array($response->status(), [408, 429], true) || ($response->successful() && $parsed['execution_status'] === null)) {
            $this->finish($submission, $attempt, 'transport_unknown', $response->status(), null, 'transport_outcome_unknown', 'Response does not prove acceptance; reconcile before retrying.', $artifact);
        } else {
            $this->finish($submission, $attempt, 'http_rejected', $response->status(), null, 'anaf_http_rejected', 'ANAF rejected the HTTP request; automatic retry is disabled.', $artifact);
        }

        return $submission->refresh();
    }

    public function checkStatus(int $submissionId, User $actor): InvoiceSubmission
    {
        Gate::forUser($actor)->authorize('customer_invoice.submit');
        $submission = InvoiceSubmission::query()->with(['issuedInvoice.legalEntity', 'attempts'])->findOrFail($submissionId);
        $this->assertEnvironment($submission->environment);
        if (blank($submission->external_message_id)) {
            throw ValidationException::withMessages(['external_message_id' => 'ANAF has not returned an upload index; reconcile an unknown upload manually.']);
        }
        $settings = config('services.efactura.environments.'.$submission->environment);
        $url = rtrim((string) ($settings['status_url'] ?? ''), '/');
        if (! $this->approvedEndpoint($url, $submission->environment, 'status')) {
            throw ValidationException::withMessages(['endpoint' => 'Status URL does not match the approved ANAF environment.']);
        }
        $token = $this->tokens->accessToken($submission->issuedInvoice->legalEntity, $submission->environment);
        $attempt = $submission->attempts()->create([
            'attempt_number' => ((int) $submission->attempts()->max('attempt_number')) + 1,
            'local_attempt_uuid' => (string) str()->uuid(), 'request_started_at' => now(),
            'transport_result' => 'status_check_in_progress', 'external_message_id' => $submission->external_message_id,
            'safe_request_metadata' => ['endpoint' => 'status', 'external_message_id' => $submission->external_message_id],
        ]);
        try {
            $response = Http::withToken($token)->connectTimeout((int) config('services.efactura.connect_timeout_seconds', 5))
                ->timeout((int) config('services.efactura.timeout_seconds', 30))->get($url, ['id_incarcare' => $submission->external_message_id]);
        } catch (Throwable) {
            $attempt->forceFill(['request_completed_at' => now(), 'transport_result' => 'status_check_failed', 'redacted_error_code' => 'status_unavailable', 'redacted_error_message' => 'ANAF status could not be retrieved.'])->save();
            throw ValidationException::withMessages(['status' => 'ANAF status could not be retrieved. The submission state was not changed.']);
        }
        $artifact = $this->storeResponse($submission->issuedInvoice, $submission, $attempt, $actor, $response);
        $state = $this->parseStatus($response);
        if (! $response->successful() || $state === 'unknown') {
            $attempt->forceFill(['request_completed_at' => now(), 'transport_result' => 'status_response_invalid', 'http_status' => $response->status(), 'response_artifact_id' => $artifact?->getKey(), 'redacted_error_code' => 'status_response_invalid', 'redacted_error_message' => 'ANAF status response could not be interpreted.', 'safe_response_metadata' => ['http_status' => $response->status()]])->save();
            throw ValidationException::withMessages(['status' => 'ANAF returned an uninterpretable status response. The response artifact was retained privately.']);
        }
        $attempt->forceFill(['request_completed_at' => now(), 'transport_result' => 'status_received', 'http_status' => $response->status(), 'response_artifact_id' => $artifact?->getKey(), 'safe_response_metadata' => ['official_processing_state' => $state]])->save();
        $submission->forceFill(['official_processing_state' => $state, 'responded_at' => now(), 'response_artifact_id' => $artifact?->getKey(), 'response_sha256' => $artifact?->sha256])->save();
        $this->event($submission->issuedInvoice, $actor, $submission, 'efactura_status_received', ['environment' => $submission->environment, 'state' => $state]);

        return $submission->refresh();
    }

    private function finish(InvoiceSubmission $submission, InvoiceSubmissionAttempt $attempt, string $result, ?int $httpStatus, ?string $externalId, ?string $errorCode, ?string $errorMessage, ?IssuedInvoiceArtifact $responseArtifact = null): void
    {
        $state = match ($result) {
            'accepted_for_processing' => 'submitted',
            'transport_unknown' => 'unknown',
            default => 'failed',
        };
        DB::transaction(function () use ($submission, $attempt, $result, $state, $httpStatus, $externalId, $errorCode, $errorMessage, $responseArtifact): void {
            $attempt->forceFill([
                'request_completed_at' => now(), 'transport_result' => $result, 'http_status' => $httpStatus,
                'external_message_id' => $externalId, 'redacted_error_code' => $errorCode,
                'redacted_error_message' => $errorMessage, 'response_artifact_id' => $responseArtifact?->getKey(),
                'safe_response_metadata' => array_filter(['http_status' => $httpStatus, 'external_message_id' => $externalId, 'response_sha256' => $responseArtifact?->sha256], fn ($v): bool => $v !== null),
            ])->save();
            $submission->forceFill([
                'transmission_state' => $state, 'external_message_id' => $externalId ?? $submission->external_message_id,
                'submitted_at' => $state === 'submitted' ? now() : $submission->submitted_at,
                'responded_at' => $httpStatus !== null ? now() : $submission->responded_at,
                'official_processing_state' => $state === 'submitted' ? 'processing' : $submission->official_processing_state,
                'redacted_error_code' => $errorCode, 'redacted_error_message' => $errorMessage,
                'response_artifact_id' => $responseArtifact?->getKey() ?? $submission->response_artifact_id,
                'response_sha256' => $responseArtifact !== null ? $responseArtifact->sha256 : $submission->response_sha256,
            ])->save();
            $this->event($submission->issuedInvoice, User::query()->find($submission->created_by), $submission, 'efactura_upload_'.$result, ['environment' => $submission->environment, 'http_status' => $httpStatus, 'error_code' => $errorCode]);
        });
    }

    private function storeResponse(IssuedInvoice $invoice, InvoiceSubmission $submission, InvoiceSubmissionAttempt $attempt, User $actor, Response $response): ?IssuedInvoiceArtifact
    {
        $body = $response->body();
        if ($body === '' || strlen($body) > 2 * 1024 * 1024) {
            return null;
        }
        $sha = hash('sha256', $body);
        $contentType = strtolower((string) $response->header('Content-Type'));
        $extension = str_contains($contentType, 'json') ? 'json' : 'xml';
        $key = 'issued-invoices/'.$invoice->public_id.'/'.$submission->invoice_snapshot_hash.'/efactura-response-'.$attempt->local_attempt_uuid.'.'.$extension;
        $disk = Storage::disk('local');
        if (! $disk->put($key, $body)) {
            return null;
        }
        try {
            return $invoice->artifacts()->create([
                'artifact_type' => 'efactura_response', 'storage_disk' => 'local', 'storage_key' => $key,
                'media_type' => $contentType !== '' ? $contentType : 'application/octet-stream', 'byte_size' => strlen($body),
                'sha256' => $sha, 'created_by' => $actor->getKey(), 'source_artifact_id' => $submission->payload_artifact_id,
            ]);
        } catch (Throwable $exception) {
            $disk->delete($key);
            throw $exception;
        }
    }

    /** @return array{execution_status: ?int, external_id: ?string} */
    private function parseUpload(Response $response): array
    {
        $body = $response->body();
        if ($body === '' || strlen($body) > 2 * 1024 * 1024 || preg_match('/<!DOCTYPE|<!ENTITY/i', $body)) {
            return ['execution_status' => null, 'external_id' => null];
        }
        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $doc->loadXML($body, LIBXML_NONET | LIBXML_NOBLANKS);
            libxml_clear_errors();
        } finally {
            libxml_use_internal_errors($previous);
        }
        if (! $loaded) {
            return ['execution_status' => null, 'external_id' => null];
        }
        $header = (new DOMXPath($doc))->query('/*[local-name()="header"]')->item(0);
        if (! $header instanceof \DOMElement || ! is_numeric($header->getAttribute('ExecutionStatus'))) {
            return ['execution_status' => null, 'external_id' => null];
        }
        $status = (int) $header->getAttribute('ExecutionStatus');
        $id = $header->getAttribute('index_incarcare') ?: null;
        if ($status === 0 && ($id === null || ! preg_match('/^\d{1,30}$/', $id))) {
            return ['execution_status' => null, 'external_id' => null];
        }

        return ['execution_status' => $status, 'external_id' => $id];
    }

    private function parseStatus(Response $response): string
    {
        $body = $response->body();
        if ($body === '' || strlen($body) > 2 * 1024 * 1024 || preg_match('/<!DOCTYPE|<!ENTITY/i', $body)) {
            return 'unknown';
        }
        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $doc->loadXML($body, LIBXML_NONET | LIBXML_NOBLANKS);
            libxml_clear_errors();
        } finally {
            libxml_use_internal_errors($previous);
        }
        if (! $loaded) {
            return 'unknown';
        }
        $xpath = new DOMXPath($doc);
        $state = strtolower(trim((string) $xpath->evaluate('string(//*[local-name()="stare"][1])')));
        if ($state === '') {
            return 'unknown';
        }

        return match ($state) {
            'ok', 'acceptata', 'acceptat' => 'accepted',
            'nok', 'respinsa', 'respins' => 'rejected',
            'in prelucrare', 'în prelucrare', 'processing' => 'processing',
            default => 'unknown',
        };
    }

    private function event(IssuedInvoice $invoice, ?User $actor, InvoiceSubmission $submission, string $type, array $metadata): void
    {
        IssuedInvoiceEvent::query()->create(['issued_invoice_id' => $invoice->getKey(), 'event_type' => $type, 'actor_id' => $actor?->getKey(), 'correlation_uuid' => $submission->correlation_uuid, 'safe_metadata' => $metadata, 'created_at' => now()]);
    }

    private function assertEnvironment(string $environment): void
    {
        if (! in_array($environment, ['test', 'production'], true)) {
            throw ValidationException::withMessages(['environment' => 'Environment must be test or production.']);
        }
    }

    private function approvedEndpoint(string $url, string $environment, string $operation): bool
    {
        $parts = parse_url($url);
        $path = '/'.($environment === 'test' ? 'test' : 'prod').'/FCTEL/rest/'.($operation === 'upload' ? 'upload' : 'stareMesaj');

        return is_array($parts) && ($parts['scheme'] ?? null) === 'https' && strtolower((string) ($parts['host'] ?? '')) === 'webserviceapl.anaf.ro'
            && ($parts['path'] ?? null) === $path && ! isset($parts['user']) && ! isset($parts['pass']);
    }
}
