<?php

namespace Tests\Feature;

use App\Models\CustomerInvoice;
use App\Models\FinancialAccount;
use App\Models\Tax;
use App\Models\Transaction;
use App\Models\VendorInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FinancialNormalizationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_reports_candidates_without_mutating_source_or_canonical_fields(): void
    {
        $transaction = $this->makeTransaction(['amount' => 12345, 'transaction_type' => 'income']);
        config(['financial-normalization.tables' => [
            'transactions' => [
                'amounts' => [['source' => 'amount', 'target' => 'amount_decimal', 'unit' => 'minor', 'non_negative' => true]],
                'classifications' => [['source' => 'transaction_type', 'target' => 'transaction_type_code', 'map' => ['income' => 'money_in']]],
            ],
        ]]);

        $this->artisan('finance:normalize-data')
            ->expectsOutputToContain('Dry run: no records or audit tables will be changed.')
            ->expectsOutputToContain('Totals: changed=1, unchanged=0, rejected=0, quarantined=0')
            ->assertExitCode(0);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'amount' => 12345,
            'amount_decimal' => null,
            'transaction_type_code' => null,
        ]);
        $this->assertDatabaseCount('financial_normalization_runs', 0);
        $this->assertDatabaseCount('financial_normalization_items', 0);
    }

    public function test_apply_is_deterministic_idempotent_and_audits_source_values(): void
    {
        $transaction = $this->makeTransaction(['amount' => 12345, 'transaction_type' => 'income']);
        config(['financial-normalization.tables' => [
            'transactions' => [
                'amounts' => [['source' => 'amount', 'target' => 'amount_decimal', 'unit' => 'minor', 'non_negative' => true]],
                'classifications' => [['source' => 'transaction_type', 'target' => 'transaction_type_code', 'map' => ['income' => 'money_in']]],
            ],
        ]]);

        $this->artisan('finance:normalize-data', ['--apply' => true])
            ->expectsOutputToContain('Totals: changed=1, unchanged=0, rejected=0, quarantined=0')
            ->assertExitCode(0);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'amount' => 12345,
            'amount_decimal' => '123.45',
            'transaction_type' => 'income',
            'transaction_type_code' => 'money_in',
        ]);
        $this->assertDatabaseHas('financial_normalization_items', [
            'source_table' => 'transactions',
            'source_id' => $transaction->id,
            'field_name' => 'amount_decimal',
            'outcome' => 'changed',
            'source_value' => '12345',
            'target_value' => '123.45',
        ]);

        $this->artisan('finance:normalize-data', ['--apply' => true])
            ->expectsOutputToContain('Totals: changed=0, unchanged=1, rejected=0, quarantined=0')
            ->assertExitCode(0);

        $this->assertDatabaseCount('financial_normalization_runs', 2);
        $this->assertDatabaseCount('financial_normalization_items', 4);
        $this->assertDatabaseHas('financial_normalization_runs', ['id' => 2, 'status' => 'completed']);
        $summary = json_decode((string) DB::table('financial_normalization_runs')->where('id', 1)->value('summary'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('passed', $summary['reconciliation']['status']);
        $this->assertSame('123.45', $summary['reconciliation']['groups']['transactions.amount_decimal|RON|money_in']['expected_total']);
        $this->assertSame('123.45', $summary['reconciliation']['groups']['transactions.amount_decimal|RON|money_in']['actual_total']);
    }

    public function test_unapproved_units_and_unknown_classifications_quarantine_the_whole_row(): void
    {
        $transaction = $this->makeTransaction(['amount' => 100, 'transaction_type' => 'mystery']);
        config(['financial-normalization.tables' => [
            'transactions' => [
                'amounts' => [['source' => 'amount', 'target' => 'amount_decimal', 'unit' => null, 'non_negative' => true]],
                'classifications' => [['source' => 'transaction_type', 'target' => 'transaction_type_code', 'map' => ['income' => 'money_in']]],
            ],
        ]]);

        $this->artisan('finance:normalize-data', ['--apply' => true])
            ->expectsOutputToContain('Totals: changed=0, unchanged=0, rejected=0, quarantined=1')
            ->assertExitCode(0);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'amount' => 100,
            'amount_decimal' => null,
            'transaction_type' => 'mystery',
            'transaction_type_code' => null,
        ]);
        $this->assertDatabaseHas('financial_normalization_items', [
            'source_table' => 'transactions',
            'source_id' => $transaction->id,
            'field_name' => 'amount_decimal',
            'outcome' => 'quarantined',
        ]);
    }

    public function test_conflicting_existing_canonical_value_is_quarantined_and_never_overwritten(): void
    {
        $transaction = $this->makeTransaction(['amount' => 12345, 'amount_decimal' => '999.99']);
        config(['financial-normalization.tables' => [
            'transactions' => [
                'amounts' => [['source' => 'amount', 'target' => 'amount_decimal', 'unit' => 'minor', 'non_negative' => true]],
            ],
        ]]);

        $this->artisan('finance:normalize-data', ['--apply' => true])
            ->expectsOutputToContain('Totals: changed=0, unchanged=0, rejected=0, quarantined=1')
            ->assertExitCode(0);

        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'amount' => 12345, 'amount_decimal' => '999.99']);
        $this->assertDatabaseHas('financial_normalization_items', [
            'source_table' => 'transactions',
            'source_id' => $transaction->id,
            'field_name' => 'amount_decimal',
            'outcome' => 'quarantined',
        ]);
    }

    public function test_negative_transaction_amount_is_quarantined_without_partial_classification_update(): void
    {
        $transaction = $this->makeTransaction(['amount' => -100, 'transaction_type' => 'income']);
        config(['financial-normalization.tables' => [
            'transactions' => [
                'amounts' => [['source' => 'amount', 'target' => 'amount_decimal', 'unit' => 'minor', 'non_negative' => true]],
                'classifications' => [['source' => 'transaction_type', 'target' => 'transaction_type_code', 'map' => ['income' => 'money_in']]],
            ],
        ]]);

        $this->artisan('finance:normalize-data', ['--apply' => true])
            ->expectsOutputToContain('Totals: changed=0, unchanged=0, rejected=0, quarantined=1')
            ->assertExitCode(0);

        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'amount' => -100, 'amount_decimal' => null, 'transaction_type_code' => null]);
    }

    public function test_malformed_amount_is_rejected_without_partial_field_updates(): void
    {
        $transaction = $this->makeTransaction(['amount' => 100, 'transaction_type' => 'income']);
        DB::table('transactions')->where('id', $transaction->id)->update(['amount' => 'not-an-integer']);
        config(['financial-normalization.tables' => [
            'transactions' => [
                'amounts' => [['source' => 'amount', 'target' => 'amount_decimal', 'unit' => 'minor', 'non_negative' => true]],
                'classifications' => [['source' => 'transaction_type', 'target' => 'transaction_type_code', 'map' => ['income' => 'money_in']]],
            ],
        ]]);

        $this->artisan('finance:normalize-data', ['--apply' => true])
            ->expectsOutputToContain('Totals: changed=0, unchanged=0, rejected=1, quarantined=0')
            ->assertExitCode(1);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'amount_decimal' => null,
            'transaction_type_code' => null,
        ]);
        $this->assertDatabaseHas('financial_normalization_runs', ['id' => 1, 'status' => 'completed']);
    }

    public function test_currency_backfill_requires_an_exact_approved_mapping(): void
    {
        $invoice = VendorInvoice::factory()->create(['month' => '2026-10-01', 'currency' => 'RON']);
        config(['financial-normalization.tables' => [
            'vendor_invoices' => [
                'currencies' => [['source' => 'currency', 'target' => 'currency_code', 'map' => ['RON' => 'RON', 'EUR' => 'EUR']]],
            ],
        ]]);

        $this->artisan('finance:normalize-data', ['--apply' => true])
            ->expectsOutputToContain('Totals: changed=1, unchanged=0, rejected=0, quarantined=0')
            ->assertExitCode(0);

        $this->assertDatabaseHas('vendor_invoices', ['id' => $invoice->id, 'currency' => 'RON', 'currency_code' => 'RON']);
    }

    public function test_unapproved_invoice_semantics_are_quarantined_without_recalculation(): void
    {
        $invoice = VendorInvoice::factory()->create([
            'month' => '2026-10-01',
            'amount' => 1200,
            'amount_without_vat' => 1000,
            'vat' => 200,
        ]);
        config(['financial-normalization.tables' => [
            'vendor_invoices' => [
                'invoice_semantics' => ['rule' => null, 'fields' => ['amount', 'amount_without_vat', 'vat']],
            ],
        ]]);

        $this->artisan('finance:normalize-data', ['--apply' => true])
            ->expectsOutputToContain('Totals: changed=0, unchanged=0, rejected=0, quarantined=1')
            ->assertExitCode(0);

        $this->assertDatabaseHas('vendor_invoices', [
            'id' => $invoice->id,
            'amount' => 1200,
            'amount_without_vat' => 1000,
            'vat' => 200,
            'amount_decimal' => null,
        ]);
        $this->assertDatabaseHas('financial_normalization_items', [
            'source_table' => 'vendor_invoices',
            'source_id' => $invoice->id,
            'field_name' => 'invoice_semantics',
            'outcome' => 'quarantined',
        ]);
    }

    public function test_migration_refuses_to_drop_normalization_audit_history(): void
    {
        DB::table('financial_normalization_runs')->insert([
            'mode' => 'apply',
            'status' => 'completed',
            'started_at' => now(),
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $migration = require database_path('migrations/2026_10_24_000001_add_financial_normalization_audit.php');

        try {
            $migration->down();
            $this->fail('Expected rollback to preserve normalization audit history.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('normalization run/audit history exists', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasTable('financial_normalization_items'));
        $this->assertDatabaseCount('financial_normalization_runs', 1);
    }

    private function makeTransaction(array $overrides = []): Transaction
    {
        $account = FinancialAccount::factory()->create();
        $invoice = CustomerInvoice::factory()->create(['month' => '2026-10-01']);
        $tax = Tax::factory()->create();

        return Transaction::factory()->create(array_merge([
            'financial_account_id' => $account->id,
            'customer_invoice_id' => $invoice->id,
            'tax_id' => $tax->id,
            'vendor_invoice_id' => null,
            'amount' => 100,
            'transaction_type' => 'income',
            'currency_code' => 'RON',
        ], $overrides));
    }
}
