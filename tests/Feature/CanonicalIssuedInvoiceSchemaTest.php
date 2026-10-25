<?php

namespace Tests\Feature;

use App\Models\CustomerInvoice;
use App\Models\IssuedInvoice;
use App\Models\LegalEntity;
use App\Models\VendorInvoice;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class CanonicalIssuedInvoiceSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_is_additive_and_preserves_legacy_invoice_values(): void
    {
        // The sequence migration depends on legal_entities, so roll it back first
        // when exercising the canonical invoice migration in isolation.
        $sequenceMigration = require database_path('migrations/2026_10_25_030000_create_issued_invoice_sequences_table.php');
        $sequenceMigration->down();

        $migration = require database_path('migrations/2026_10_25_020000_create_canonical_issued_invoice_tables.php');
        $migration->down();

        $legacy = CustomerInvoice::factory()->create([
            'month' => '2026-10-01', 'amount_without_vat' => 12345, 'vat' => 2500,
        ]);
        $vendor = VendorInvoice::factory()->create([
            'amount' => 2000, 'amount_without_vat' => 1800, 'vat' => 200,
            'currency' => 'RON', 'month' => '2026-10-01', 'invoice_number' => 'SUP-001',
        ]);
        $migration->up();
        $sequenceMigration->up();

        $this->assertDatabaseHas('customer_invoices', [
            'id' => $legacy->id, 'amount_without_vat' => 12345, 'vat' => 2500,
        ]);
        $this->assertDatabaseHas('vendor_invoices', [
            'id' => $vendor->id, 'amount_without_vat' => 1800, 'vat' => 200, 'currency' => 'RON',
        ]);

        $entity = LegalEntity::query()->create([
            'legal_name' => 'Synthetic Issuer SRL', 'country_code' => 'RO',
            'tax_identifier_type' => 'CUI', 'tax_identifier' => 'TEST-ISSUER-01',
        ]);
        $invoice = IssuedInvoice::query()->create([
            'legal_entity_id' => $entity->id,
            'legacy_customer_invoice_id' => $legacy->id,
            'document_type' => IssuedInvoice::TYPE_INVOICE,
        ]);

        foreach ([
            'legal_entities', 'issued_invoices', 'issued_invoice_events', 'issued_invoice_parties', 'issued_invoice_lines',
            'issued_invoice_line_taxes', 'issued_invoice_tax_breakdowns', 'issued_invoice_artifacts',
            'invoice_submissions', 'invoice_submission_attempts',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing {$table}");
        }
        $this->assertNotEmpty($invoice->public_id);
        $this->assertSame('draft', $invoice->fresh()->document_status);
        $this->assertSame(12345, (int) $legacy->fresh()->amount_without_vat);
        $this->assertSame(2500, (int) $legacy->fresh()->vat);
        $this->assertDatabaseHas('vendor_invoices', [
            'id' => $vendor->id, 'amount_without_vat' => 1800, 'vat' => 200,
            'currency' => 'RON', 'invoice_number' => 'SUP-001',
        ]);
        $this->assertDatabaseHas('issued_invoices', [
            'id' => $invoice->id, 'legacy_customer_invoice_id' => $legacy->id,
            'invoice_number' => null, 'currency_code' => null, 'net_total' => null,
            'tax_total' => null, 'gross_total' => null,
        ]);
    }

    public function test_schema_uses_decimal_money_and_separate_document_payment_and_transmission_states(): void
    {
        $decimals = [
            'issued_invoices' => ['net_total', 'tax_total', 'gross_total'],
            'issued_invoice_lines' => ['quantity', 'unit_price_net', 'discount_amount', 'net_amount'],
            'issued_invoice_line_taxes' => ['tax_rate_percent', 'taxable_base', 'tax_amount'],
            'issued_invoice_tax_breakdowns' => ['tax_rate_percent', 'taxable_base', 'tax_amount'],
        ];
        foreach ($decimals as $table => $columns) {
            foreach ($columns as $column) {
                $this->assertContains(strtoupper(Schema::getColumnType($table, $column)), ['DECIMAL', 'NUMERIC']);
            }
        }
        $this->assertTrue(Schema::hasColumn('issued_invoices', 'document_status'));
        $this->assertTrue(Schema::hasColumn('issued_invoices', 'payment_status'));
        $this->assertTrue(Schema::hasColumn('invoice_submissions', 'transmission_state'));
        $this->assertTrue(Schema::hasColumn('invoice_submissions', 'official_processing_state'));
        $this->assertTrue(Schema::hasColumn('issued_invoice_artifacts', 'sha256'));
    }

    public function test_model_relationships_persist_line_tax_artifact_and_submission_attempt_history(): void
    {
        $entity = LegalEntity::query()->create([
            'legal_name' => 'Synthetic Issuer SRL', 'country_code' => 'RO',
            'tax_identifier_type' => 'CUI', 'tax_identifier' => 'TEST-ISSUER-02',
        ]);
        $invoice = IssuedInvoice::query()->create([
            'legal_entity_id' => $entity->id, 'document_type' => IssuedInvoice::TYPE_INVOICE,
            'currency_code' => 'RON', 'net_total' => '100.00', 'tax_total' => '21.00', 'gross_total' => '121.00',
        ]);
        $invoice->events()->create([
            'event_type' => 'draft_created', 'to_status' => 'draft',
            'safe_metadata' => ['source' => 'schema-test'],
        ]);
        $invoice->parties()->create(['role' => 'seller', 'legal_name' => $entity->legal_name, 'country_code' => 'RO']);
        $line = $invoice->lines()->create([
            'line_number' => 1, 'description' => 'Synthetic service', 'quantity' => '1.000000',
            'unit_code' => 'C62', 'unit_price_net' => '100.000000', 'discount_amount' => '0.00', 'net_amount' => '100.00',
        ]);
        $line->taxes()->create([
            'tax_scheme_code' => 'VAT', 'tax_category_code' => 'TEST-ONLY',
            'tax_rate_percent' => '21.0000', 'taxable_base' => '100.00', 'tax_amount' => '21.00',
        ]);
        $invoice->taxBreakdowns()->create([
            'tax_scheme_code' => 'VAT', 'tax_category_code' => 'TEST-ONLY',
            'tax_rate_percent' => '21.0000', 'taxable_base' => '100.00', 'tax_amount' => '21.00',
        ]);
        $artifact = $invoice->artifacts()->create([
            'artifact_type' => 'canonical_payload', 'storage_disk' => 'local',
            'storage_key' => 'synthetic/invoice.xml', 'media_type' => 'application/xml',
            'byte_size' => 100, 'sha256' => str_repeat('a', 64),
        ]);
        $submission = $invoice->submissions()->create([
            'invoice_snapshot_hash' => str_repeat('b', 64), 'environment' => 'test',
            'applicability_decision' => 'required', 'payload_artifact_id' => $artifact->id,
            'payload_sha256' => str_repeat('a', 64), 'correlation_uuid' => '5fce6d65-3f0a-4b77-ae63-2a27b0ac1e3b',
            'transmission_state' => 'queued',
        ]);
        $submission->attempts()->create([
            'attempt_number' => 1, 'local_attempt_uuid' => 'd41f5f4d-8c41-45d2-9a2f-37b9e8314c3a',
            'request_started_at' => now(), 'transport_result' => 'not_sent',
            'safe_request_metadata' => ['operation' => 'synthetic-test'],
        ]);

        $fresh = $invoice->fresh(['legalEntity', 'events', 'parties', 'lines.taxes', 'taxBreakdowns', 'submissions.attempts']);
        $this->assertSame($entity->id, $fresh->legalEntity->id);
        $this->assertSame('draft_created', $fresh->events->sole()->event_type);
        $this->assertSame('schema-test', $fresh->events->sole()->safe_metadata['source']);
        $this->assertSame('seller', $fresh->parties->sole()->role);
        $this->assertSame('21.00', $fresh->lines->sole()->taxes->sole()->tax_amount);
        $this->assertSame($artifact->id, $fresh->submissions->sole()->payloadArtifact->id);
        $this->assertSame(1, $fresh->submissions->sole()->attempts->sole()->attempt_number);
    }

    public function test_database_constraints_prevent_duplicate_numbering_provenance_party_roles_and_line_numbers(): void
    {
        $entity = LegalEntity::query()->create([
            'legal_name' => 'Synthetic Issuer SRL', 'country_code' => 'RO',
            'tax_identifier_type' => 'CUI', 'tax_identifier' => 'TEST-ISSUER-03',
        ]);
        $legacy = CustomerInvoice::factory()->create(['month' => '2026-10-01']);
        IssuedInvoice::query()->create([
            'legal_entity_id' => $entity->id, 'legacy_customer_invoice_id' => $legacy->id,
            'document_type' => IssuedInvoice::TYPE_INVOICE, 'series' => 'A', 'invoice_number' => '1001',
        ]);
        try {
            IssuedInvoice::query()->create([
                'legal_entity_id' => $entity->id, 'document_type' => IssuedInvoice::TYPE_INVOICE,
                'series' => 'A', 'invoice_number' => '1001',
            ]);
            $this->fail('Duplicate legal number was accepted.');
        } catch (QueryException) {
            $this->assertDatabaseCount('issued_invoices', 1);
        }
        try {
            IssuedInvoice::query()->create([
                'legal_entity_id' => $entity->id, 'legacy_customer_invoice_id' => $legacy->id,
                'document_type' => IssuedInvoice::TYPE_INVOICE,
            ]);
            $this->fail('Duplicate legacy link was accepted.');
        } catch (QueryException) {
            $this->assertDatabaseCount('issued_invoices', 1);
        }

        $invoice = IssuedInvoice::query()->firstOrFail();
        $party = ['role' => 'buyer', 'legal_name' => 'Synthetic Buyer SRL', 'country_code' => 'RO'];
        $invoice->parties()->create($party);
        try {
            $invoice->parties()->create($party);
            $this->fail('Duplicate party role was accepted.');
        } catch (QueryException) {
            $this->assertDatabaseCount('issued_invoice_parties', 1);
        }
        $line = [
            'description' => 'Synthetic line', 'quantity' => '1.000000', 'unit_code' => 'C62',
            'unit_price_net' => '1.000000', 'discount_amount' => '0.00', 'net_amount' => '1.00',
        ];
        $invoice->lines()->create(['line_number' => 1, ...$line]);
        try {
            $invoice->lines()->create(['line_number' => 1, ...$line]);
            $this->fail('Duplicate line number was accepted.');
        } catch (QueryException) {
            $this->assertDatabaseCount('issued_invoice_lines', 1);
        }
    }

    public function test_mysql_check_constraints_reject_invalid_document_status_and_line_metadata(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL-specific CHECK constraints are exercised by the MySQL CI job.');
        }

        $entity = LegalEntity::query()->create([
            'legal_name' => 'Synthetic Issuer SRL', 'country_code' => 'RO',
            'tax_identifier_type' => 'CUI', 'tax_identifier' => 'TEST-ISSUER-CHECK',
        ]);
        try {
            IssuedInvoice::query()->create([
                'legal_entity_id' => $entity->id,
                'document_type' => IssuedInvoice::TYPE_INVOICE,
                'document_status' => 'not-a-supported-state',
            ]);
            $this->fail('Unsupported document status was accepted by MySQL.');
        } catch (QueryException) {
            $this->assertDatabaseCount('issued_invoices', 0);
        }

        $invoice = IssuedInvoice::query()->create([
            'legal_entity_id' => $entity->id,
            'document_type' => IssuedInvoice::TYPE_INVOICE,
        ]);
        try {
            $invoice->lines()->create([
                'line_number' => 0, 'description' => 'Invalid line', 'quantity' => '1.000000',
                'unit_code' => 'C62', 'unit_price_net' => '1.000000',
                'discount_amount' => '0.00', 'net_amount' => '1.00',
            ]);
            $this->fail('Zero line number was accepted by MySQL.');
        } catch (QueryException) {
            $this->assertDatabaseCount('issued_invoice_lines', 0);
        }
    }

    public function test_submission_attempts_are_unique_and_rollback_refuses_to_drop_invoice_history(): void
    {
        $entity = LegalEntity::query()->create([
            'legal_name' => 'Synthetic Issuer SRL', 'country_code' => 'RO',
            'tax_identifier_type' => 'CUI', 'tax_identifier' => 'TEST-ISSUER-04',
        ]);
        $invoice = IssuedInvoice::query()->create([
            'legal_entity_id' => $entity->id, 'document_type' => IssuedInvoice::TYPE_INVOICE,
            'issued_snapshot_hash' => str_repeat('c', 64),
        ]);
        $submission = $invoice->submissions()->create([
            'invoice_snapshot_hash' => str_repeat('c', 64), 'environment' => 'test',
            'applicability_decision' => 'required', 'correlation_uuid' => '5fce6d65-3f0a-4b77-ae63-2a27b0ac1e3b',
        ]);
        $attempt = [
            'attempt_number' => 1, 'local_attempt_uuid' => 'd41f5f4d-8c41-45d2-9a2f-37b9e8314c3a',
            'request_started_at' => now(), 'transport_result' => 'not_sent',
        ];
        $submission->attempts()->create($attempt);
        try {
            $submission->attempts()->create($attempt);
            $this->fail('Duplicate attempt number was accepted.');
        } catch (QueryException) {
            $this->assertDatabaseCount('invoice_submission_attempts', 1);
        }

        $migration = require database_path('migrations/2026_10_25_020000_create_canonical_issued_invoice_tables.php');
        try {
            $migration->down();
            $this->fail('Rollback dropped populated invoice history.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('invoice_submission_attempts', $exception->getMessage());
        }
        $this->assertTrue(Schema::hasTable('issued_invoices'));
        $this->assertDatabaseHas('invoice_submission_attempts', ['attempt_number' => 1]);
    }
}
