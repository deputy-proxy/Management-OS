<?php

namespace Tests\Feature;

use App\Models\IssuedInvoice;
use App\Models\LegalEntity;
use App\Models\User;
use App\Services\Finance\IssuedInvoiceArtifactAccessService;
use App\Services\Finance\IssuedInvoiceArtifactRetentionService;
use App\Services\Finance\IssuedInvoiceDocumentService;
use App\Services\Finance\IssuedInvoiceLifecycleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class IssuedInvoiceArtifactRetentionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Gate::define('customer_invoice.index', fn (): bool => true);
        Gate::define('customer_invoice.document.download', fn (): bool => true);
        Gate::define('customer_invoice.create', fn (): bool => true);
        Gate::define('customer_invoice.update', fn (): bool => true);
        Gate::define('customer_invoice.retention_manage', fn (): bool => true);
        Gate::define('customer_invoice.retention_review', fn (): bool => true);
    }

    public function test_private_artifact_access_checks_hash_and_records_only_safe_metadata(): void
    {
        $actor = User::factory()->create();
        $invoice = $this->issuedInvoice($actor);
        $artifact = app(IssuedInvoiceDocumentService::class)->generate($invoice->id, $actor);
        $result = app(IssuedInvoiceArtifactAccessService::class)->read($artifact->id, $actor);

        $this->assertSame('local', $artifact->storage_disk);
        $this->assertSame($artifact->sha256, hash('sha256', $result['content']));
        $this->assertSame($artifact->byte_size, $result['byte_size']);
        $this->assertSame('text/html; charset=UTF-8', $result['media_type']);
        $this->assertDatabaseHas('issued_invoice_events', [
            'issued_invoice_id' => $invoice->id, 'event_type' => 'invoice_artifact_downloaded', 'actor_id' => $actor->id,
        ]);
        $event = $invoice->events()->where('event_type', 'invoice_artifact_downloaded')->firstOrFail();
        $this->assertSame(['artifact_id', 'artifact_type', 'sha256', 'byte_size', 'media_type'], array_keys($event->safe_metadata));
        $this->assertStringNotContainsString($result['content'], json_encode($event->safe_metadata));

        Storage::disk('local')->put($artifact->storage_key, 'tampered bytes');
        try {
            app(IssuedInvoiceArtifactAccessService::class)->read($artifact->id, $actor);
            $this->fail('Tampered evidence was returned.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('artifact', $exception->errors());
        }
        $this->assertSame(1, $invoice->events()->where('event_type', 'invoice_artifact_downloaded')->count());
    }

    public function test_private_artifact_access_is_denied_without_invoice_view_permission(): void
    {
        $actor = User::factory()->create();
        $invoice = $this->issuedInvoice($actor);
        $artifact = app(IssuedInvoiceDocumentService::class)->generate($invoice->id, $actor);
        Gate::define('customer_invoice.document.download', fn (): bool => false);

        $this->expectException(AuthorizationException::class);
        app(IssuedInvoiceArtifactAccessService::class)->read($artifact->id, $actor);
    }

    public function test_artifacts_are_not_deletable_and_retention_policy_and_legal_hold_changes_are_audited(): void
    {
        $actor = User::factory()->create();
        $invoice = $this->issuedInvoice($actor);
        $artifact = app(IssuedInvoiceDocumentService::class)->generate($invoice->id, $actor);
        $originalHash = $artifact->sha256;

        $this->assertSame('rendered_invoice_derivative', $artifact->retention_category);
        $this->assertSame('pending_review', $artifact->retention_review_status);
        $this->assertFalse($artifact->legal_hold);
        $this->assertNull($artifact->retention_policy_reference);
        try {
            $artifact->forceFill(['sha256' => str_repeat('f', 64)])->save();
            $this->fail('Immutable evidence hash was changed.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('immutable', $exception->getMessage());
        }

        $retention = app(IssuedInvoiceArtifactRetentionService::class);
        $reviewed = $retention->recordApprovedPolicy($artifact->id, $actor, 'FINANCE-RETENTION-REVIEW-2026-01');
        $this->assertSame('approved', $reviewed->retention_review_status);
        $this->assertSame('FINANCE-RETENTION-REVIEW-2026-01', $reviewed->retention_policy_reference);
        $this->assertSame($actor->id, $reviewed->retention_reviewed_by);

        $held = $retention->placeLegalHold($artifact->id, $actor, 'Open audit request REF-2026-15');
        $this->assertTrue($held->legal_hold);
        $this->assertSame('Open audit request REF-2026-15', $held->legal_hold_reason);
        try {
            $artifact->delete();
            $this->fail('Invoice evidence was deleted through Eloquent.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('cannot be deleted', $exception->getMessage());
        }
        $this->assertDatabaseHas('issued_invoice_artifacts', ['id' => $artifact->id, 'sha256' => $originalHash]);

        $released = $retention->releaseLegalHold($artifact->id, $actor, 'Audit request closed under REF-2026-15');
        $this->assertFalse($released->legal_hold);
        $this->assertSame('approved', $released->retention_review_status);
        $this->assertDatabaseHas('issued_invoice_events', ['issued_invoice_id' => $invoice->id, 'event_type' => 'artifact_retention_policy_reviewed']);
        $this->assertDatabaseHas('issued_invoice_events', ['issued_invoice_id' => $invoice->id, 'event_type' => 'artifact_legal_hold_placed']);
        $this->assertDatabaseHas('issued_invoice_events', ['issued_invoice_id' => $invoice->id, 'event_type' => 'artifact_legal_hold_released']);
    }

    public function test_retention_policy_review_requires_an_explicit_policy_reference(): void
    {
        $actor = User::factory()->create();
        $invoice = $this->issuedInvoice($actor);
        $artifact = app(IssuedInvoiceDocumentService::class)->generate($invoice->id, $actor);

        try {
            app(IssuedInvoiceArtifactRetentionService::class)->recordApprovedPolicy($artifact->id, $actor, ' ');
            $this->fail('An empty retention policy reference was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('retention_policy_reference', $exception->errors());
        }
        $this->assertSame('pending_review', $artifact->fresh()->retention_review_status);
    }

    private function issuedInvoice(User $actor): IssuedInvoice
    {
        $entity = LegalEntity::query()->create([
            'legal_name' => 'Retention Issuer SRL', 'country_code' => 'RO', 'tax_identifier_type' => 'CUI',
            'tax_identifier' => 'RETENTION-'.uniqid(), 'is_active' => true,
        ]);
        $service = app(IssuedInvoiceLifecycleService::class);
        $draft = $service->createDraft($actor, $this->payload($entity), $this->policy());
        $service->approve($draft->id, $actor);

        return $service->issue($draft->id, $actor, ['version' => 'retention-numbering-v1', 'series' => 'RET-2026', 'prefix' => 'RET-', 'padding' => 5, 'start_at' => 1]);
    }

    private function payload(LegalEntity $entity): array
    {
        return [
            'legal_entity_id' => $entity->id, 'document_type' => IssuedInvoice::TYPE_INVOICE,
            'issue_date' => '2026-10-25', 'currency_code' => 'RON',
            'parties' => [
                'seller' => ['legal_name' => $entity->legal_name, 'country_code' => 'RO', 'tax_identifier_type' => 'CUI', 'tax_identifier' => 'RETENTION-ISSUER'],
                'buyer' => ['legal_name' => 'Retention Buyer SRL', 'country_code' => 'RO', 'tax_identifier_type' => 'CUI', 'tax_identifier' => 'RETENTION-BUYER'],
            ],
            'lines' => [[
                'line_number' => 1, 'description' => 'Retention evidence fixture', 'quantity' => '1.000000', 'unit_code' => 'C62',
                'unit_price_net' => '100.000000', 'discount_amount' => '10.00', 'net_amount' => '90.00',
                'taxes' => [['tax_scheme_code' => 'VAT', 'tax_category_code' => 'STANDARD', 'tax_rate_percent' => '21.0000', 'taxable_base' => '90.00', 'tax_amount' => '18.90']],
            ]],
            'totals' => ['net_total' => '90.00', 'tax_total' => '18.90', 'gross_total' => '108.90'],
        ];
    }

    private function policy(): array
    {
        return [
            'calculation' => ['version' => 'retention-calculation-v1', 'rounding_mode' => 'HALF_UP', 'allowed_currencies' => ['RON'], 'buyer_tax_identifier_required' => true,
                'tax_categories' => [['tax_scheme_code' => 'VAT', 'tax_category_code' => 'STANDARD', 'rate_percent' => '21.0000', 'requires_exemption_reason' => false, 'allows_reverse_charge' => false]]],
            'numbering' => ['version' => 'retention-numbering-v1', 'series' => 'RET-2026', 'prefix' => 'RET-', 'padding' => 5, 'start_at' => 1],
        ];
    }
}
