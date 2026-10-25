<?php

namespace Tests\Feature;

use App\Models\IssuedInvoice;
use App\Models\IssuedInvoiceArtifact;
use App\Models\LegalEntity;
use App\Models\User;
use App\Services\Finance\IssuedInvoiceLifecycleService;
use App\Services\Finance\IssuedInvoiceUblGenerationService;
use App\Services\Finance\RomanianEfacturaUblValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class IssuedInvoiceUblGenerationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_issued_invoice_generates_schema_and_profile_validated_private_xml_and_reuses_artifact(): void
    {
        Storage::fake('local');
        $this->allowFinanceActions();
        $actor = User::factory()->create();
        $invoice = $this->issuedInvoice($actor);
        $service = app(IssuedInvoiceUblGenerationService::class);
        $artifact = $service->generate($invoice->id, $actor);
        $xml = Storage::disk('local')->get($artifact->storage_key);
        $this->assertSame('ubl_xml', $artifact->artifact_type);
        $this->assertSame('application/xml; charset=UTF-8', $artifact->media_type);
        $this->assertSame(hash('sha256', $xml), $artifact->sha256);
        $this->assertSame(strlen($xml), $artifact->byte_size);
        $this->assertStringContainsString('CIUS-RO:1.0.1', $xml);
        $this->assertStringContainsString('RO-2026/INV-00041', $xml);
        $this->assertSame(1, IssuedInvoiceArtifact::query()->where('issued_invoice_id', $invoice->id)->where('artifact_type', 'ubl_xml')->count());
        $again = $service->generate($invoice->id, $actor);
        $this->assertSame($artifact->id, $again->id);
        $this->assertSame($xml, Storage::disk('local')->get($again->storage_key));
    }

    public function test_credit_note_contains_original_document_reference(): void
    {
        Storage::fake('local');
        $this->allowFinanceActions();
        $actor = User::factory()->create();
        $entity = $this->issuer();
        $lifecycle = app(IssuedInvoiceLifecycleService::class);
        $draft = $lifecycle->createDraft($actor, $this->payload($entity), $this->policy());
        $lifecycle->approve($draft->id, $actor);
        $original = $lifecycle->issue($draft->id, $actor, $this->numberingPolicy());
        $payload = $this->payload($original->legalEntity);
        $payload['document_type'] = IssuedInvoice::TYPE_CREDIT_NOTE;
        $payload['correction_reason'] = 'Synthetic correction';
        $credit = $lifecycle->createCorrection($original->id, $actor, $payload, $this->policy());
        $lifecycle->approve($credit->id, $actor);
        $credit = $lifecycle->issue($credit->id, $actor, ['version' => 'credit-numbering-v1', 'series' => 'CN-2026', 'prefix' => 'CN-', 'padding' => 5, 'start_at' => 1]);
        $artifact = app(IssuedInvoiceUblGenerationService::class)->generate($credit->id, $actor);
        $xml = Storage::disk('local')->get($artifact->storage_key);
        $this->assertStringContainsString('CreditNote-2', $xml);
        $this->assertStringContainsString('<cbc:CreditNoteTypeCode>381</cbc:CreditNoteTypeCode>', $xml);
        $this->assertStringContainsString('<cac:BillingReference>', $xml);
        $this->assertStringContainsString('RO-2026/INV-00041', $xml);
    }

    public function test_drafts_and_unsupported_debit_notes_are_rejected(): void
    {
        Storage::fake('local');
        $this->allowFinanceActions();
        $actor = User::factory()->create();
        $entity = $this->issuer();
        $lifecycle = app(IssuedInvoiceLifecycleService::class);
        $draft = $lifecycle->createDraft($actor, $this->payload($entity), $this->policy());
        try {
            app(IssuedInvoiceUblGenerationService::class)->generate($draft->id, $actor);
            $this->fail('Draft was serialized.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('document_status', $exception->errors());
        }
        $original = $this->issuedInvoice($actor);
        $payload = $this->payload($original->legalEntity);
        $payload['document_type'] = IssuedInvoice::TYPE_DEBIT_NOTE;
        $payload['correction_reason'] = 'Unsupported debit note';
        $debit = $lifecycle->createCorrection($original->id, $actor, $payload, $this->policy());
        $lifecycle->approve($debit->id, $actor);
        $debit = $lifecycle->issue($debit->id, $actor, ['version' => 'debit-numbering-v1', 'series' => 'DN-2026', 'prefix' => 'DN-', 'padding' => 5, 'start_at' => 1]);
        try {
            app(IssuedInvoiceUblGenerationService::class)->generate($debit->id, $actor);
            $this->fail('Unsupported debit note was serialized.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('document_type', $exception->errors());
        }
        $this->assertSame(0, IssuedInvoiceArtifact::query()->where('artifact_type', 'ubl_xml')->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_changed_electronic_endpoint_is_rejected_against_issued_snapshot(): void
    {
        Storage::fake('local');
        $this->allowFinanceActions();
        $actor = User::factory()->create();
        $invoice = $this->issuedInvoice($actor);
        $invoice->parties()->where('role', 'buyer')->update(['endpoint_id' => '1234567890999']);

        try {
            app(IssuedInvoiceUblGenerationService::class)->generate($invoice->id, $actor);
            $this->fail('Modified electronic endpoint was serialized despite snapshot mismatch.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('invoice', $exception->errors());
        }

        $this->assertSame(0, IssuedInvoiceArtifact::query()->where('artifact_type', 'ubl_xml')->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_validator_rejects_malformed_xml_and_dtds(): void
    {
        foreach (['<Invoice', '<!DOCTYPE Invoice [<!ENTITY x "boom">]><Invoice/>'] as $xml) {
            try {
                app(RomanianEfacturaUblValidator::class)->validate($xml);
                $this->fail('Invalid XML was accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('payload', $exception->errors());
            }
        }
    }

    private function allowFinanceActions(): void
    {
        $gate = Mockery::mock();
        $gate->shouldReceive('authorize')->withAnyArgs()->andReturnTrue();
        Gate::shouldReceive('forUser')->withAnyArgs()->andReturn($gate);
    }

    private function issuedInvoice(User $actor): IssuedInvoice
    {
        $entity = $this->issuer();
        $service = app(IssuedInvoiceLifecycleService::class);
        $draft = $service->createDraft($actor, $this->payload($entity), $this->policy());
        $service->approve($draft->id, $actor);

        return $service->issue($draft->id, $actor, $this->numberingPolicy());
    }

    private function issuer(): LegalEntity
    {
        return LegalEntity::query()->create(['legal_name' => 'Synthetic Issuer SRL', 'country_code' => 'RO', 'tax_identifier_type' => 'CUI', 'tax_identifier' => '1234'.random_int(1000, 9999), 'vat_registration_id' => 'RO12345675', 'registration_id' => 'J12/1234/2026', 'address_line_1' => 'Strada Emitentului 1', 'locality' => 'Cluj-Napoca', 'postal_code' => '400000', 'region' => 'RO-CJ', 'email' => 'seller@example.test', 'is_active' => true]);
    }

    /** @return array<string, mixed> */
    private function payload(LegalEntity $entity): array
    {
        return ['legal_entity_id' => $entity->id, 'document_type' => IssuedInvoice::TYPE_INVOICE, 'issue_date' => '2026-10-25', 'supply_date' => '2026-10-25', 'due_date' => '2026-11-24', 'currency_code' => 'RON', 'parties' => ['seller' => ['legal_name' => $entity->legal_name, 'country_code' => 'RO', 'tax_identifier_type' => 'CUI', 'tax_identifier' => '1234'.random_int(1000, 9999), 'vat_registration_id' => 'RO12345675', 'registration_id' => 'J12/1234/2026', 'address_line_1' => 'Strada Emitentului 1', 'locality' => 'Cluj-Napoca', 'postal_code' => '400000', 'region' => 'RO-CJ', 'email' => 'seller@example.test', 'endpoint_id' => '1234567890123', 'endpoint_scheme_id' => '0088'], 'buyer' => ['legal_name' => 'Synthetic Buyer SRL', 'country_code' => 'RO', 'tax_identifier_type' => 'CUI', 'tax_identifier' => '87654320', 'vat_registration_id' => 'RO87654320', 'registration_id' => 'J12/5678/2026', 'address_line_1' => 'Strada Clientului 2', 'locality' => 'Cluj-Napoca', 'postal_code' => '400001', 'region' => 'RO-CJ', 'email' => 'buyer@example.test', 'endpoint_id' => '1234567890130', 'endpoint_scheme_id' => '0088']], 'lines' => [['line_number' => 1, 'description' => 'Synthetic service', 'quantity' => '1.000000', 'unit_code' => 'C62', 'unit_price_net' => '100.000000', 'discount_amount' => '10.00', 'net_amount' => '90.00', 'taxes' => [['tax_scheme_code' => 'VAT', 'tax_category_code' => 'STANDARD', 'tax_rate_percent' => '21.0000', 'taxable_base' => '90.00', 'tax_amount' => '18.90']]]], 'totals' => ['net_total' => '90.00', 'tax_total' => '18.90', 'gross_total' => '108.90']];
    }

    /** @return array<string, mixed> */
    private function policy(): array
    {
        return ['calculation' => ['version' => 'synthetic-calculation-v1', 'rounding_mode' => 'HALF_UP', 'allowed_currencies' => ['RON'], 'buyer_tax_identifier_required' => true, 'tax_categories' => [['tax_scheme_code' => 'VAT', 'tax_category_code' => 'STANDARD', 'rate_percent' => '21.0000', 'requires_exemption_reason' => false, 'allows_reverse_charge' => false]]], 'numbering' => $this->numberingPolicy()];
    }

    /** @return array<string, mixed> */
    private function numberingPolicy(): array
    {
        return ['version' => 'synthetic-numbering-v1', 'series' => 'RO-2026', 'prefix' => 'INV-', 'padding' => 5, 'start_at' => 41];
    }
}
