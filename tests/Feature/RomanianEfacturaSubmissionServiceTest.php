<?php

namespace Tests\Feature;

use App\Models\InvoiceSubmission;
use App\Models\InvoiceSubmissionAttempt;
use App\Models\InvoiceSubmissionOAuthCredential;
use App\Models\IssuedInvoice;
use App\Models\LegalEntity;
use App\Models\User;
use App\Services\Finance\IssuedInvoiceLifecycleService;
use App\Services\Finance\IssuedInvoiceUblGenerationService;
use App\Services\Finance\RomanianEfacturaSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class RomanianEfacturaSubmissionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('x', 32)),
            'services.efactura.environments.test.client_id' => 'test-client-id',
            'services.efactura.environments.test.client_secret' => 'test-client-secret',
            'services.efactura.environments.test.refresh_token' => 'test-refresh-token',
            'services.efactura.timeout_seconds' => 3,
            'services.efactura.connect_timeout_seconds' => 1,
        ]);
        Storage::fake('local');
        $gate = Mockery::mock();
        $gate->shouldReceive('authorize')->withAnyArgs()->andReturnTrue();
        Gate::shouldReceive('forUser')->withAnyArgs()->andReturn($gate);
    }

    public function test_upload_uses_test_environment_and_oauth_then_duplicate_call_is_idempotent_and_status_is_captured(): void
    {
        Http::fake([
            'https://logincert.anaf.ro/anaf-oauth2/v1/token' => Http::response(['access_token' => 'test-access-token', 'refresh_token' => 'rotated-refresh-token', 'expires_in' => 3600], 200),
            'https://api.anaf.ro/test/FCTEL/rest/upload*' => Http::response('<header ExecutionStatus="0" index_incarcare="12345"/>', 200, ['Content-Type' => 'application/xml']),
            'https://api.anaf.ro/test/FCTEL/rest/stareMesaj*' => Http::response('<header stare="ok" id_descarcare="56789"/>', 200, ['Content-Type' => 'application/xml']),
            'https://api.anaf.ro/test/FCTEL/rest/descarcare*' => Http::response($this->zipPayload(['Factura.xml' => '<Invoice/>', 'semnatura.xml' => '<Signature/>']), 200, ['Content-Type' => 'application/zip']),
        ]);
        $actor = User::factory()->create();
        $invoice = $this->issuedInvoice($actor);
        app(IssuedInvoiceUblGenerationService::class)->generate($invoice->id, $actor);
        $service = app(RomanianEfacturaSubmissionService::class);

        $submission = $service->submit($invoice->id, 'test', $actor, 'required', 'Approved applicability review 2026-10-25');
        $this->assertSame('submitted', $submission->transmission_state);
        $this->assertSame('12345', $submission->external_message_id);
        $this->assertSame('test', $submission->environment);
        $this->assertSame($invoice->issued_snapshot_hash, $submission->invoice_snapshot_hash);
        $this->assertSame(1, $submission->attempts()->count());
        $this->assertSame(1, InvoiceSubmission::query()->count());

        $again = $service->submit($invoice->id, 'test', $actor, 'required', 'Approved applicability review 2026-10-25');
        $this->assertSame($submission->id, $again->id);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/test/FCTEL/rest/upload?')
            && str_contains($request->url(), 'standard=UBL') && str_contains($request->url(), 'cif=12345675')
            && $request->hasHeader('Authorization', 'Bearer test-access-token')
            && str_contains($request->body(), 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2'));
        $this->assertSame(1, collect(Http::recorded())->filter(fn (array $pair): bool => str_contains($pair[0]->url(), '/FCTEL/rest/upload?'))->count());

        $checked = $service->checkStatus($submission->id, $actor);
        $this->assertSame('processed', $checked->official_processing_state);
        $this->assertNotNull($checked->response_artifact_id);
        $this->assertSame('downloaded', $checked->download_state);
        $this->assertNotNull($checked->result_artifact_id);
        $this->assertSame(3, $checked->attempts()->count());
        $credential = app(InvoiceSubmissionOAuthCredential::class)->newQuery()->where('legal_entity_id', $invoice->legal_entity_id)->where('environment', 'test')->firstOrFail();
        $this->assertSame('rotated-refresh-token', $credential->refresh_token);
        $this->assertStringNotContainsString('rotated-refresh-token', (string) DB::table('invoice_submission_oauth_credentials')->where('id', $credential->id)->value('refresh_token'));
    }

    public function test_ambiguous_upload_timeout_is_marked_unknown_and_never_automatically_retried(): void
    {
        Http::fake([
            'https://logincert.anaf.ro/anaf-oauth2/v1/token' => Http::response(['access_token' => 'test-access-token', 'refresh_token' => 'rotated-refresh-token', 'expires_in' => 3600], 200),
            'https://api.anaf.ro/test/FCTEL/rest/upload*' => function (): never {
                throw new ConnectionException('synthetic timeout');
            },
        ]);
        $actor = User::factory()->create();
        $invoice = $this->issuedInvoice($actor);
        app(IssuedInvoiceUblGenerationService::class)->generate($invoice->id, $actor);
        $service = app(RomanianEfacturaSubmissionService::class);
        try {
            $service->submit($invoice->id, 'test', $actor);
            $this->fail('Ambiguous timeout was not surfaced.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('submission', $exception->errors());
        }
        $submission = InvoiceSubmission::query()->firstOrFail();
        $this->assertSame('unknown', $submission->transmission_state);
        $this->assertSame('transport_unknown', $submission->attempts()->firstOrFail()->transport_result);
        try {
            $service->submit($invoice->id, 'test', $actor);
            $this->fail('Unknown outcome was retried.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('submission', $exception->errors());
        }
        $this->assertSame(1, $submission->attempts()->count());
    }

    public function test_oauth_failure_before_upload_can_be_safely_retried_after_secret_correction(): void
    {
        Http::fake([
            'https://logincert.anaf.ro/anaf-oauth2/v1/token' => Http::response(['access_token' => 'test-access-token', 'refresh_token' => 'rotated-refresh-token', 'expires_in' => 3600], 200),
            'https://api.anaf.ro/test/FCTEL/rest/upload*' => Http::response('<header ExecutionStatus="0" index_incarcare="54321"/>', 200, ['Content-Type' => 'application/xml']),
        ]);
        $actor = User::factory()->create();
        $invoice = $this->issuedInvoice($actor);
        app(IssuedInvoiceUblGenerationService::class)->generate($invoice->id, $actor);
        config(['services.efactura.environments.test.client_secret' => null]);
        try {
            app(RomanianEfacturaSubmissionService::class)->submit($invoice->id, 'test', $actor);
            $this->fail('Missing OAuth secret was not rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('authorization', $exception->errors());
        }
        $submission = InvoiceSubmission::query()->firstOrFail();
        $this->assertSame('failed', $submission->transmission_state);
        $this->assertSame('auth_failed', $submission->attempts()->firstOrFail()->transport_result);
        config(['services.efactura.environments.test.client_secret' => 'corrected-test-secret']);
        $retried = app(RomanianEfacturaSubmissionService::class)->submit($invoice->id, 'test', $actor);
        $this->assertSame('submitted', $retried->transmission_state);
        $this->assertSame(2, $retried->attempts()->count());
        $this->assertSame(1, collect(Http::recorded())->filter(fn (array $pair): bool => str_contains($pair[0]->url(), '/FCTEL/rest/upload?'))->count());
    }

    public function test_review_required_decision_can_be_explicitly_approved_before_submission(): void
    {
        Http::fake([
            'https://logincert.anaf.ro/anaf-oauth2/v1/token' => Http::response(['access_token' => 'test-access-token', 'refresh_token' => 'rotated-refresh-token', 'expires_in' => 3600], 200),
            'https://api.anaf.ro/test/FCTEL/rest/upload*' => Http::response('<header ExecutionStatus="0" index_incarcare="98765"/>', 200, ['Content-Type' => 'application/xml']),
        ]);
        $actor = User::factory()->create();
        $invoice = $this->issuedInvoice($actor);
        app(IssuedInvoiceUblGenerationService::class)->generate($invoice->id, $actor);
        $service = app(RomanianEfacturaSubmissionService::class);
        $blocked = $service->submit($invoice->id, 'test', $actor, 'review_required', 'Finance review is pending.');
        $this->assertSame('blocked', $blocked->transmission_state);
        $this->assertSame(0, InvoiceSubmissionAttempt::query()->count());
        $approved = $service->submit($invoice->id, 'test', $actor, 'required', 'Finance reviewed applicability and approved submission.');
        $this->assertSame('submitted', $approved->transmission_state);
        $this->assertSame('required', $approved->applicability_decision);
        $this->assertSame('Finance reviewed applicability and approved submission.', $approved->applicability_reason);
        $this->assertSame(1, $approved->attempts()->count());
    }

    public function test_tampered_payload_is_rejected_before_any_network_request(): void
    {
        Http::fake();
        $actor = User::factory()->create();
        $invoice = $this->issuedInvoice($actor);
        app(IssuedInvoiceUblGenerationService::class)->generate($invoice->id, $actor);
        $artifact = $invoice->fresh()->artifacts()->where('artifact_type', 'ubl_xml')->firstOrFail();
        Storage::disk($artifact->storage_disk)->put($artifact->storage_key, '<Invoice>tampered</Invoice>');

        try {
            app(RomanianEfacturaSubmissionService::class)->submit($invoice->id, 'test', $actor);
            $this->fail('Tampered XML was submitted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payload_artifact', $exception->errors());
        }

        $this->assertSame(0, InvoiceSubmission::query()->count());
        $this->assertCount(0, Http::recorded());
    }

    public function test_nok_status_downloads_the_signed_error_archive_and_marks_the_submission_terminal(): void
    {
        Http::fake([
            'https://logincert.anaf.ro/anaf-oauth2/v1/token' => Http::response(['access_token' => 'test-access-token', 'refresh_token' => 'rotated-refresh-token', 'expires_in' => 3600], 200),
            'https://api.anaf.ro/test/FCTEL/rest/upload*' => Http::response('<header ExecutionStatus="0" index_incarcare="45678"/>', 200, ['Content-Type' => 'application/xml']),
            'https://api.anaf.ro/test/FCTEL/rest/stareMesaj*' => Http::response('<header stare="nok" id_descarcare="56780"/>', 200, ['Content-Type' => 'application/xml']),
            'https://api.anaf.ro/test/FCTEL/rest/descarcare*' => Http::response($this->zipPayload([
                'Erori.xml' => '<header><Error cod="BR-S-05" errorMessage="Sensitive VAT detail"/></header>',
                'semnatura.xml' => '<Signature/>',
            ]), 200, ['Content-Type' => 'application/zip']),
        ]);
        $actor = User::factory()->create();
        $invoice = $this->issuedInvoice($actor);
        $snapshotHash = $invoice->issued_snapshot_hash;
        app(IssuedInvoiceUblGenerationService::class)->generate($invoice->id, $actor);
        $service = app(RomanianEfacturaSubmissionService::class);
        $submission = $service->submit($invoice->id, 'test', $actor);
        $checked = $service->checkStatus($submission->id, $actor);

        $this->assertSame('rejected', $checked->official_processing_state);
        $this->assertSame('failed', $checked->transmission_state);
        $this->assertSame('downloaded', $checked->download_state);
        $this->assertSame($snapshotHash, $invoice->fresh()->issued_snapshot_hash);
        $this->assertSame('BR-S-05', $checked->errors()->firstOrFail()->authority_code);
        $this->assertSame('terminal', $checked->errors()->firstOrFail()->retry_classification);
        $this->assertStringNotContainsString('Sensitive VAT detail', $checked->errors()->firstOrFail()->safe_summary);
        $this->assertStringContainsString('Sensitive VAT detail', $this->zipEntry(Storage::disk('local')->get($checked->resultArtifact->storage_key), 'Erori.xml'));
    }

    public function test_corrected_invoice_submission_links_to_the_original_submission(): void
    {
        $uploadSequence = Http::sequence()
            ->push('<header ExecutionStatus="0" index_incarcare="10001"/>', 200, ['Content-Type' => 'application/xml'])
            ->push('<header ExecutionStatus="0" index_incarcare="10002"/>', 200, ['Content-Type' => 'application/xml']);
        Http::fake([
            'https://logincert.anaf.ro/anaf-oauth2/v1/token' => Http::response(['access_token' => 'test-access-token', 'refresh_token' => 'rotated-refresh-token', 'expires_in' => 3600], 200),
            'https://api.anaf.ro/test/FCTEL/rest/upload*' => $uploadSequence,
        ]);
        $actor = User::factory()->create();
        $original = $this->issuedInvoice($actor);
        app(IssuedInvoiceUblGenerationService::class)->generate($original->id, $actor);
        $service = app(RomanianEfacturaSubmissionService::class);
        $originalSubmission = $service->submit($original->id, 'test', $actor);

        $entity = $original->legalEntity;
        $payload = $this->payload($entity);
        $payload['document_type'] = IssuedInvoice::TYPE_CREDIT_NOTE;
        $payload['correction_reason_code'] = 'TEST-CORRECTION';
        $payload['correction_reason'] = 'Synthetic correction fixture';
        $lifecycle = app(IssuedInvoiceLifecycleService::class);
        $draft = $lifecycle->createCorrection($original->id, $actor, $payload, $this->policy());
        $lifecycle->approve($draft->id, $actor);
        $correction = $lifecycle->issue($draft->id, $actor, [
            'version' => 'test-numbering-v1', 'series' => 'RO-2026', 'prefix' => 'INV-', 'padding' => 5, 'start_at' => 1,
        ]);
        app(IssuedInvoiceUblGenerationService::class)->generate($correction->id, $actor);
        $correctionSubmission = $service->submit($correction->id, 'test', $actor);

        $this->assertSame($originalSubmission->id, $correctionSubmission->corrects_submission_id);
        $this->assertSame($original->id, $correctionSubmission->correctsSubmission->issued_invoice_id);
        $this->assertSame($original->issued_snapshot_hash, $original->fresh()->issued_snapshot_hash);
        $this->assertSame('10002', $correctionSubmission->external_message_id);
    }

    public function test_explicit_not_required_decision_is_audited_without_any_network_request(): void
    {
        Http::fake();
        $actor = User::factory()->create();
        $invoice = $this->issuedInvoice($actor);
        $submission = app(RomanianEfacturaSubmissionService::class)->submit($invoice->id, 'test', $actor, 'not_required', 'Outside current statutory scope, reviewed by finance.');
        $this->assertSame('not_required', $submission->transmission_state);
        $this->assertSame('not_required', $submission->applicability_decision);
        $this->assertSame(0, InvoiceSubmissionAttempt::query()->count());
        $this->assertCount(0, Http::recorded());
    }

    public function test_an_af_rejection_is_captured_privately_and_error_is_redacted_from_submission_record(): void
    {
        Http::fake([
            'https://logincert.anaf.ro/anaf-oauth2/v1/token' => Http::response(['access_token' => 'test-access-token', 'refresh_token' => 'rotated-refresh-token', 'expires_in' => 3600], 200),
            'https://api.anaf.ro/test/FCTEL/rest/upload*' => Http::response('<header ExecutionStatus="1"><Errors errorMessage="Sensitive tax detail"/></header>', 200, ['Content-Type' => 'application/xml']),
        ]);
        $actor = User::factory()->create();
        $invoice = $this->issuedInvoice($actor);
        app(IssuedInvoiceUblGenerationService::class)->generate($invoice->id, $actor);
        $submission = app(RomanianEfacturaSubmissionService::class)->submit($invoice->id, 'test', $actor);
        $this->assertSame('failed', $submission->transmission_state);
        $this->assertSame('anaf_upload_rejected', $submission->redacted_error_code);
        $this->assertStringNotContainsString('Sensitive tax detail', (string) $submission->redacted_error_message);
        $this->assertDatabaseHas('invoice_submission_errors', ['invoice_submission_id' => $submission->id, 'retry_classification' => 'terminal']);
        $response = $submission->responseArtifact;
        $this->assertNotNull($response);
        $this->assertStringContainsString('Sensitive tax detail', Storage::disk('local')->get($response->storage_key));
    }

    private function zipEntry(string $bytes, string $name): string
    {
        $path = tempnam(sys_get_temp_dir(), 'efactura-test-read-');
        if ($path === false) {
            throw new \RuntimeException('Cannot create ZIP read fixture.');
        }
        try {
            file_put_contents($path, $bytes);
            $zip = new \ZipArchive;
            if ($zip->open($path) !== true) {
                throw new \RuntimeException('Cannot open ZIP read fixture.');
            }
            $content = $zip->getFromName($name);
            $zip->close();

            return is_string($content) ? $content : '';
        } finally {
            @unlink($path);
        }
    }

    private function zipPayload(array $files): string
    {
        $path = tempnam(sys_get_temp_dir(), 'efactura-test-');
        if ($path === false) {
            throw new \RuntimeException('Cannot create ZIP fixture.');
        }
        try {
            $zip = new \ZipArchive;
            if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Cannot open ZIP fixture.');
            }
            foreach ($files as $name => $content) {
                $zip->addFromString($name, $content);
            }
            $zip->close();

            return (string) file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }

    private function issuedInvoice(User $actor): IssuedInvoice
    {
        $entity = LegalEntity::query()->create([
            'legal_name' => 'Synthetic Issuer SRL', 'country_code' => 'RO', 'tax_identifier_type' => 'CUI',
            'tax_identifier' => '12345675', 'vat_registration_id' => 'RO12345675', 'registration_id' => 'J12/1234/2026',
            'address_line_1' => 'Strada Emitentului 1', 'locality' => 'Cluj-Napoca', 'postal_code' => '400000', 'region' => 'RO-CJ', 'is_active' => true,
        ]);
        $service = app(IssuedInvoiceLifecycleService::class);
        $draft = $service->createDraft($actor, $this->payload($entity), $this->policy());
        $service->approve($draft->id, $actor);

        return $service->issue($draft->id, $actor, ['version' => 'test-numbering-v1', 'series' => 'RO-2026', 'prefix' => 'INV-', 'padding' => 5, 'start_at' => 1]);
    }

    private function payload(LegalEntity $entity): array
    {
        return ['legal_entity_id' => $entity->id, 'document_type' => IssuedInvoice::TYPE_INVOICE, 'issue_date' => '2026-10-25', 'supply_date' => '2026-10-25', 'due_date' => '2026-11-24', 'currency_code' => 'RON',
            'parties' => [
                'seller' => ['legal_name' => $entity->legal_name, 'country_code' => 'RO', 'tax_identifier_type' => 'CUI', 'tax_identifier' => '12345675', 'vat_registration_id' => 'RO12345675', 'registration_id' => 'J12/1234/2026', 'address_line_1' => 'Strada Emitentului 1', 'locality' => 'Cluj-Napoca', 'postal_code' => '400000', 'region' => 'RO-CJ', 'endpoint_id' => '1234567890123', 'endpoint_scheme_id' => '0088'],
                'buyer' => ['legal_name' => 'Synthetic Buyer SRL', 'country_code' => 'RO', 'tax_identifier_type' => 'CUI', 'tax_identifier' => '87654320', 'vat_registration_id' => 'RO87654320', 'registration_id' => 'J12/5678/2026', 'address_line_1' => 'Strada Clientului 2', 'locality' => 'Cluj-Napoca', 'postal_code' => '400001', 'region' => 'RO-CJ', 'endpoint_id' => '1234567890130', 'endpoint_scheme_id' => '0088'],
            ], 'lines' => [['line_number' => 1, 'description' => 'Synthetic service', 'quantity' => '1.000000', 'unit_code' => 'C62', 'unit_price_net' => '100.000000', 'discount_amount' => '10.00', 'net_amount' => '90.00', 'taxes' => [['tax_scheme_code' => 'VAT', 'tax_category_code' => 'STANDARD', 'tax_rate_percent' => '21.0000', 'taxable_base' => '90.00', 'tax_amount' => '18.90']]]], 'totals' => ['net_total' => '90.00', 'tax_total' => '18.90', 'gross_total' => '108.90']];
    }

    private function policy(): array
    {
        return ['calculation' => ['version' => 'synthetic-calculation-v1', 'rounding_mode' => 'HALF_UP', 'allowed_currencies' => ['RON'], 'buyer_tax_identifier_required' => true, 'tax_categories' => [['tax_scheme_code' => 'VAT', 'tax_category_code' => 'STANDARD', 'rate_percent' => '21.0000', 'requires_exemption_reason' => false, 'allows_reverse_charge' => false]]], 'numbering' => ['version' => 'test-numbering-v1', 'series' => 'RO-2026', 'prefix' => 'INV-', 'padding' => 5, 'start_at' => 1]];
    }
}
