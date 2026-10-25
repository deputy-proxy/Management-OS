<?php

namespace Tests\Feature;

use App\Models\IssuedInvoice;
use App\Models\IssuedInvoiceArtifact;
use App\Models\IssuedInvoiceEvent;
use App\Models\LegalEntity;
use App\Models\User;
use App\Services\Finance\IssuedInvoiceArtifactAccessService;
use App\Services\Finance\IssuedInvoiceDocumentService;
use App\Services\Finance\IssuedInvoiceLifecycleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class IssuedInvoiceDocumentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_issued_invoice_generates_private_hashed_printable_document_and_reuses_it(): void
    {
        Storage::fake('local');
        $this->allowFinanceActions();
        $actor = User::factory()->create();
        $invoice = $this->issuedInvoice($actor);
        $service = app(IssuedInvoiceDocumentService::class);

        $artifact = $service->generate($invoice->id, $actor);
        $html = Storage::disk('local')->get($artifact->storage_key);

        $this->assertSame('invoice_html', $artifact->artifact_type);
        $this->assertSame('text/html; charset=UTF-8', $artifact->media_type);
        $this->assertSame(hash('sha256', $html), $artifact->sha256);
        $this->assertSame(strlen($html), $artifact->byte_size);
        $this->assertStringContainsString('Factură', $html);
        $this->assertStringContainsString('INV-00041', $html);
        $this->assertStringContainsString('Synthetic Buyer SRL', $html);
        $this->assertStringContainsString('108,90 RON', $html);
        $this->assertStringContainsString($invoice->issued_snapshot_hash, $html);
        $this->assertStringContainsString('nu reprezintă XML e-Factura', $html);
        $this->assertSame(1, IssuedInvoiceArtifact::query()->where('issued_invoice_id', $invoice->id)->count());

        $again = $service->generate($invoice->id, $actor);
        $this->assertSame($artifact->id, $again->id);
        $this->assertSame($html, Storage::disk('local')->get($again->storage_key));
        $this->assertSame(1, IssuedInvoiceArtifact::query()->where('issued_invoice_id', $invoice->id)->count());
    }

    public function test_document_renderer_escapes_untrusted_invoice_text(): void
    {
        Storage::fake('local');
        $this->allowFinanceActions();
        $actor = User::factory()->create();
        $invoice = $this->issuedInvoice($actor, '<script>alert("x")</script>');
        $artifact = app(IssuedInvoiceDocumentService::class)->generate($invoice->id, $actor);
        $html = Storage::disk('local')->get($artifact->storage_key);

        $this->assertStringNotContainsString('<script>alert("x")</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', $html);
    }

    public function test_changed_relational_data_is_rejected_when_it_no_longer_matches_issued_hash(): void
    {
        Storage::fake('local');
        $this->allowFinanceActions();
        $actor = User::factory()->create();
        $invoice = $this->issuedInvoice($actor);
        $lineId = $invoice->lines()->value('id');
        $invoice->lines()->whereKey($lineId)->update(['description' => 'tampered description']);

        try {
            app(IssuedInvoiceDocumentService::class)->generate($invoice->id, $actor);
            $this->fail('Modified invoice data was rendered despite the immutable snapshot hash mismatch.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('invoice', $exception->errors());
        }

        $this->assertSame(0, IssuedInvoiceArtifact::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_draft_invoice_cannot_be_rendered_as_an_issued_document(): void
    {
        Storage::fake('local');
        $this->allowFinanceActions();
        $actor = User::factory()->create();
        $entity = $this->issuer();
        $draft = app(IssuedInvoiceLifecycleService::class)->createDraft($actor, $this->payload($entity), $this->policy());

        try {
            app(IssuedInvoiceDocumentService::class)->generate($draft->id, $actor);
            $this->fail('A draft invoice was rendered as an issued document.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('document_status', $exception->errors());
        }

        $this->assertSame(0, IssuedInvoiceArtifact::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_corrupt_existing_artifact_is_not_silently_replaced(): void
    {
        Storage::fake('local');
        $this->allowFinanceActions();
        $actor = User::factory()->create();
        $invoice = $this->issuedInvoice($actor);
        $service = app(IssuedInvoiceDocumentService::class);
        $artifact = $service->generate($invoice->id, $actor);
        Storage::disk('local')->put($artifact->storage_key, 'tampered');

        try {
            $service->generate($invoice->id, $actor);
            $this->fail('Corrupt generated artifact was silently accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('artifact', $exception->errors());
        }
        $this->assertSame(1, IssuedInvoiceArtifact::query()->where('issued_invoice_id', $invoice->id)->count());
    }

    public function test_private_artifact_download_requires_permission_checks_integrity_and_writes_audit_event(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->allowFinanceActions();
        $actor = User::factory()->create();
        $invoice = $this->issuedInvoice($actor);
        $artifact = app(IssuedInvoiceDocumentService::class)->generate($invoice->id, $actor);

        $response = $this->actingAs($actor)->get(route('finance.issued-invoice-artifacts.download', ['artifact' => $artifact->id]));

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertHeader('Content-Disposition', 'attachment; filename="'.$invoice->public_id.'-invoice_html-'.substr($artifact->sha256, 0, 12).'.html"');
        $this->assertSame(Storage::disk('local')->get($artifact->storage_key), $response->getContent());
        $this->assertSame([], Storage::disk('public')->allFiles());

        $event = IssuedInvoiceEvent::query()->where('issued_invoice_id', $invoice->id)->where('event_type', 'invoice_artifact_downloaded')->firstOrFail();
        $this->assertSame($actor->id, $event->actor_id);
        $this->assertSame($artifact->sha256, $event->safe_metadata['sha256']);
        $this->assertArrayNotHasKey('content', $event->safe_metadata);
        $this->assertArrayNotHasKey('storage_key', $event->safe_metadata);
    }

    public function test_private_artifact_download_denies_users_without_the_dedicated_permission(): void
    {
        Storage::fake('local');
        $denyDownload = false;
        $gate = Mockery::mock();
        $gate->shouldReceive('authorize')->withAnyArgs()->andReturnUsing(function (string $ability) use (&$denyDownload): void {
            if ($denyDownload && $ability === 'customer_invoice.document.download') {
                throw new AuthorizationException('Artifact download denied.');
            }
        });
        Gate::shouldReceive('forUser')->withAnyArgs()->andReturn($gate);

        $actor = User::factory()->create();
        $invoice = $this->issuedInvoice($actor);
        $artifact = app(IssuedInvoiceDocumentService::class)->generate($invoice->id, $actor);
        $denyDownload = true;

        try {
            app(IssuedInvoiceArtifactAccessService::class)->read($artifact->id, $actor);
            $this->fail('Artifact bytes were returned without the dedicated download permission.');
        } catch (AuthorizationException) {
            $this->assertSame(0, IssuedInvoiceEvent::query()->where('event_type', 'invoice_artifact_downloaded')->count());
        }
    }

    public function test_private_artifact_download_refuses_tampered_bytes_and_does_not_audit_the_download(): void
    {
        Storage::fake('local');
        $this->allowFinanceActions();
        $actor = User::factory()->create();
        $invoice = $this->issuedInvoice($actor);
        $artifact = app(IssuedInvoiceDocumentService::class)->generate($invoice->id, $actor);
        Storage::disk('local')->put($artifact->storage_key, 'tampered bytes');

        try {
            app(IssuedInvoiceArtifactAccessService::class)->read($artifact->id, $actor);
            $this->fail('Tampered artifact was returned to the caller.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('artifact', $exception->errors());
        }

        $this->assertSame(0, IssuedInvoiceEvent::query()->where('event_type', 'invoice_artifact_downloaded')->count());
    }

    private function allowFinanceActions(): void
    {
        $gate = Mockery::mock();
        $gate->shouldReceive('authorize')->withAnyArgs()->andReturnTrue();
        Gate::shouldReceive('forUser')->withAnyArgs()->andReturn($gate);
    }

    private function issuedInvoice(User $actor, string $description = 'Synthetic service'): IssuedInvoice
    {
        $entity = $this->issuer();
        $payload = $this->payload($entity);
        $payload['lines'][0]['description'] = $description;
        $service = app(IssuedInvoiceLifecycleService::class);
        $draft = $service->createDraft($actor, $payload, $this->policy());
        $service->approve($draft->id, $actor);

        return $service->issue($draft->id, $actor, $this->numberingPolicy());
    }

    private function issuer(): LegalEntity
    {
        return LegalEntity::query()->create([
            'legal_name' => 'Synthetic Issuer SRL', 'country_code' => 'RO',
            'tax_identifier_type' => 'CUI', 'tax_identifier' => 'TEST-ISSUER-'.uniqid(), 'is_active' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(LegalEntity $entity): array
    {
        return [
            'legal_entity_id' => $entity->id, 'document_type' => IssuedInvoice::TYPE_INVOICE,
            'issue_date' => '2026-10-25', 'supply_date' => '2026-10-25', 'due_date' => '2026-11-24', 'currency_code' => 'RON',
            'parties' => [
                'seller' => ['legal_name' => $entity->legal_name, 'country_code' => 'RO', 'tax_identifier_type' => 'CUI', 'tax_identifier' => 'TEST-ISSUER'],
                'buyer' => ['legal_name' => 'Synthetic Buyer SRL', 'country_code' => 'RO', 'tax_identifier_type' => 'CUI', 'tax_identifier' => 'TEST-BUYER'],
            ],
            'lines' => [[
                'line_number' => 1, 'description' => 'Synthetic service', 'quantity' => '1.000000',
                'unit_code' => 'C62', 'unit_price_net' => '100.000000', 'discount_amount' => '10.00', 'net_amount' => '90.00',
                'taxes' => [[
                    'tax_scheme_code' => 'VAT', 'tax_category_code' => 'STANDARD', 'tax_rate_percent' => '21.0000',
                    'taxable_base' => '90.00', 'tax_amount' => '18.90',
                ]],
            ]],
            'totals' => ['net_total' => '90.00', 'tax_total' => '18.90', 'gross_total' => '108.90'],
        ];
    }

    /** @return array<string, mixed> */
    private function policy(): array
    {
        return [
            'calculation' => [
                'version' => 'synthetic-calculation-v1', 'rounding_mode' => 'HALF_UP',
                'allowed_currencies' => ['RON'], 'buyer_tax_identifier_required' => true,
                'tax_categories' => [[
                    'tax_scheme_code' => 'VAT', 'tax_category_code' => 'STANDARD', 'rate_percent' => '21.0000',
                    'requires_exemption_reason' => false, 'allows_reverse_charge' => false,
                ]],
            ],
            'numbering' => $this->numberingPolicy(),
        ];
    }

    /** @return array<string, mixed> */
    private function numberingPolicy(): array
    {
        return ['version' => 'synthetic-numbering-v1', 'series' => 'RO-2026', 'prefix' => 'INV-', 'padding' => 5, 'start_at' => 41];
    }
}
