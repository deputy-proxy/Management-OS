<?php

namespace Tests\Feature;

use App\Models\IssuedInvoice;
use App\Models\LegalEntity;
use App\Models\User;
use App\Services\Finance\IssuedInvoiceLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class IssuedInvoiceLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_approval_and_issue_allocate_a_versioned_number_and_retain_a_snapshot_hash(): void
    {
        $this->allowFinanceActions();
        $actor = User::factory()->create();
        $entity = $this->issuer();
        $service = app(IssuedInvoiceLifecycleService::class);
        $draft = $service->createDraft($actor, $this->payload($entity), $this->policy());

        $this->assertSame(IssuedInvoice::STATUS_DRAFT, $draft->document_status);
        $this->assertSame('108.90', $draft->gross_total);
        $this->assertCount(1, $draft->lines);
        $this->assertCount(1, $draft->events);

        $approved = $service->approve($draft->id, $actor);
        $this->assertSame(IssuedInvoice::STATUS_APPROVED, $approved->document_status);
        $this->assertSame($actor->id, $approved->approved_by);

        $issued = $service->issue($draft->id, $actor, $this->numberingPolicy());
        $this->assertSame(IssuedInvoice::STATUS_ISSUED, $issued->document_status);
        $this->assertSame('INV-00041', $issued->invoice_number);
        $this->assertSame('RO-2026', $issued->series);
        $this->assertSame($actor->id, $issued->issued_by);
        $this->assertSame(64, strlen($issued->issued_snapshot_hash));
        $this->assertDatabaseHas('issued_invoice_sequences', [
            'legal_entity_id' => $entity->id, 'series' => 'RO-2026', 'next_number' => 42,
        ]);
        $this->assertDatabaseHas('issued_invoice_events', [
            'issued_invoice_id' => $issued->id, 'event_type' => 'issued', 'to_status' => 'issued',
        ]);
    }

    public function test_issued_snapshot_cannot_be_edited_or_deleted(): void
    {
        $entity = $this->issuer();
        $invoice = IssuedInvoice::query()->create([
            'legal_entity_id' => $entity->id, 'document_type' => IssuedInvoice::TYPE_INVOICE,
            'document_status' => IssuedInvoice::STATUS_ISSUED, 'invoice_number' => 'INV-9',
            'series' => 'RO', 'currency_code' => 'RON', 'net_total' => '1.00',
            'tax_total' => '0.00', 'gross_total' => '1.00', 'issued_snapshot_hash' => str_repeat('a', 64),
        ]);

        try {
            $invoice->forceFill(['gross_total' => '999.00'])->save();
            $this->fail('An issued invoice was edited.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('immutable', $exception->getMessage());
        }

        try {
            $invoice->delete();
            $this->fail('An issued invoice was deleted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('cannot be deleted', $exception->getMessage());
        }
    }

    public function test_invalid_lifecycle_transitions_are_rejected_and_do_not_consume_a_number(): void
    {
        $this->allowFinanceActions();
        $actor = User::factory()->create();
        $entity = $this->issuer();
        $draft = app(IssuedInvoiceLifecycleService::class)->createDraft($actor, $this->payload($entity), $this->policy());

        try {
            app(IssuedInvoiceLifecycleService::class)->issue($draft->id, $actor, $this->numberingPolicy());
            $this->fail('An unapproved draft was issued.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('document_status', $exception->errors());
        }

        $this->assertDatabaseMissing('issued_invoice_sequences', ['legal_entity_id' => $entity->id]);
        $this->assertSame(IssuedInvoice::STATUS_DRAFT, $draft->fresh()->document_status);
    }

    public function test_approved_invoice_can_be_cancelled_only_with_a_reason(): void
    {
        $this->allowFinanceActions();
        $actor = User::factory()->create();
        $entity = $this->issuer();
        $service = app(IssuedInvoiceLifecycleService::class);
        $draft = $service->createDraft($actor, $this->payload($entity), $this->policy());
        $service->approve($draft->id, $actor);
        $cancelled = $service->cancelDraft($draft->id, $actor, 'Duplicate internal request');

        $this->assertSame(IssuedInvoice::STATUS_CANCELLED, $cancelled->document_status);
        $this->assertDatabaseMissing('issued_invoice_sequences', ['legal_entity_id' => $entity->id]);
        $this->assertDatabaseHas('issued_invoice_events', [
            'issued_invoice_id' => $draft->id, 'event_type' => 'draft_cancelled', 'from_status' => 'approved',
        ]);
    }

    public function test_correction_is_a_new_issued_document_and_preserves_the_original_snapshot(): void
    {
        $this->allowFinanceActions();
        $actor = User::factory()->create();
        $entity = $this->issuer();
        $service = app(IssuedInvoiceLifecycleService::class);
        $originalDraft = $service->createDraft($actor, $this->payload($entity), $this->policy());
        $service->approve($originalDraft->id, $actor);
        $original = $service->issue($originalDraft->id, $actor, $this->numberingPolicy());
        $originalHash = $original->issued_snapshot_hash;

        $correctionPayload = $this->payload($entity);
        $correctionPayload['document_type'] = IssuedInvoice::TYPE_CREDIT_NOTE;
        $correctionPayload['correction_reason_code'] = 'TEST-CORRECTION';
        $correctionPayload['correction_reason'] = 'Synthetic correction fixture';
        $correction = $service->createCorrection($original->id, $actor, $correctionPayload, $this->policy());
        $service->approve($correction->id, $actor);
        $issuedCorrection = $service->issue($correction->id, $actor, $this->numberingPolicy());

        $this->assertSame($original->id, $issuedCorrection->corrects_issued_invoice_id);
        $this->assertSame(IssuedInvoice::STATUS_CORRECTED, $original->fresh()->document_status);
        $this->assertSame($originalHash, $original->fresh()->issued_snapshot_hash);
        $this->assertSame('INV-00042', $issuedCorrection->invoice_number);
        $this->assertDatabaseHas('issued_invoice_events', [
            'issued_invoice_id' => $original->id, 'event_type' => 'corrected_by_new_document',
        ]);
    }

    public function test_numbering_policy_version_cannot_change_an_existing_sequence_silently(): void
    {
        $this->allowFinanceActions();
        $actor = User::factory()->create();
        $entity = $this->issuer();
        $service = app(IssuedInvoiceLifecycleService::class);
        $draft = $service->createDraft($actor, $this->payload($entity), $this->policy());
        $service->approve($draft->id, $actor);
        $service->issue($draft->id, $actor, $this->numberingPolicy());

        $next = $service->createDraft($actor, $this->payload($entity), $this->policy());
        $service->approve($next->id, $actor);
        $policy = $this->numberingPolicy();
        $policy['version'] = 'numbering-v2';

        try {
            $service->issue($next->id, $actor, $policy);
            $this->fail('A changed numbering policy was silently applied to an existing sequence.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('numbering_policy.version', $exception->errors());
        }

        $this->assertSame(IssuedInvoice::STATUS_APPROVED, $next->fresh()->document_status);
        $this->assertDatabaseHas('issued_invoice_sequences', ['legal_entity_id' => $entity->id, 'next_number' => 42]);
    }

    private function allowFinanceActions(): void
    {
        $gate = Mockery::mock();
        $gate->shouldReceive('authorize')->withAnyArgs()->andReturnTrue();
        Gate::shouldReceive('forUser')->withAnyArgs()->andReturn($gate);
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
            'issue_date' => '2026-10-25', 'currency_code' => 'RON',
            'parties' => [
                'seller' => ['legal_name' => $entity->legal_name, 'country_code' => 'RO', 'tax_identifier_type' => 'CUI', 'tax_identifier' => 'TEST-ISSUER'],
                'buyer' => ['legal_name' => 'Synthetic Buyer SRL', 'country_code' => 'RO', 'tax_identifier_type' => 'CUI', 'tax_identifier' => 'TEST-BUYER'],
            ],
            'lines' => [[
                'line_number' => 1, 'description' => 'Synthetic service', 'quantity' => '1.000000',
                'unit_code' => 'C62', 'unit_price_net' => '100.000000', 'discount_amount' => '10.00',
                'net_amount' => '90.00', 'taxes' => [[
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
