<?php

namespace Tests\Feature;

use App\Models\CustomerInvoice;
use App\Models\FinancialAccount;
use App\Models\FinancialAccountBalance;
use App\Models\ProductCost;
use App\Models\ProductResource;
use App\Models\ProductRevenue;
use App\Models\Tax;
use App\Models\Transaction;
use App\Models\VendorInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class CanonicalFinancialSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_preserves_preexisting_legacy_rows_when_added_to_an_existing_schema(): void
    {
        $migration = require database_path('migrations/2026_10_24_000000_add_canonical_financial_amount_columns.php');
        $migration->down();

        $account = FinancialAccount::factory()->create(['currency' => 'EUR']);
        $invoice = CustomerInvoice::factory()->create([
            'month' => '2026-10-01',
            'amount_without_vat' => 12345,
            'vat' => 0,
        ]);
        $tax = Tax::factory()->create(['amount' => 75]);
        $transaction = Transaction::factory()->create([
            'financial_account_id' => $account->id,
            'customer_invoice_id' => $invoice->id,
            'tax_id' => $tax->id,
            'vendor_invoice_id' => null,
            'amount' => 12345,
        ]);

        $migration->up();

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'amount' => 12345,
            'amount_decimal' => null,
            'currency_code' => null,
        ]);
        $this->assertDatabaseHas('customer_invoices', [
            'id' => $invoice->id,
            'amount_without_vat' => 12345,
            'vat' => 0,
            'amount_without_vat_decimal' => null,
            'vat_decimal' => null,
            'currency_code' => null,
        ]);
        $this->assertDatabaseHas('taxes', [
            'id' => $tax->id,
            'amount' => 75,
            'amount_decimal' => null,
            'currency_code' => null,
        ]);
    }

    public function test_additive_canonical_amount_and_currency_columns_are_nullable_and_two_decimal(): void
    {
        $expected = [
            'transactions' => ['amount_decimal', 'currency_code'],
            'customer_invoices' => ['amount_without_vat_decimal', 'vat_decimal', 'currency_code'],
            'vendor_invoices' => ['amount_decimal', 'amount_without_vat_decimal', 'vat_decimal', 'currency_code'],
            'taxes' => ['amount_decimal', 'currency_code'],
            'financial_account_balances' => ['current_balance_decimal', 'currency_code'],
            'product_resources' => ['amount_decimal', 'currency_code'],
            'product_costs' => ['resource_amount_decimal', 'allocated_amount_decimal', 'currency_code'],
            'product_revenues' => ['amount_decimal', 'currency_code'],
        ];

        foreach ($expected as $table => $columns) {
            foreach ($columns as $column) {
                $this->assertTrue(Schema::hasColumn($table, $column), "Missing {$table}.{$column}");
                $this->assertDatabaseMissing($table, [$column => '__never_matches__']);
            }
        }

        $this->assertContains(strtoupper(Schema::getColumnType('transactions', 'amount_decimal')), ['DECIMAL', 'NUMERIC']);
        $this->assertContains(strtoupper(Schema::getColumnType('product_costs', 'allocated_amount_decimal')), ['DECIMAL', 'NUMERIC']);
    }

    public function test_existing_legacy_values_remain_unchanged_and_canonical_columns_stay_null(): void
    {
        $account = FinancialAccount::factory()->create(['currency' => 'EUR']);
        $invoice = CustomerInvoice::factory()->create(['month' => '2026-10-01', 'amount_without_vat' => 1250, 'vat' => 0]);
        $tax = Tax::factory()->create(['amount' => 75]);
        $transaction = Transaction::factory()->create([
            'financial_account_id' => $account->id,
            'customer_invoice_id' => $invoice->id,
            'tax_id' => $tax->id,
            'vendor_invoice_id' => null,
            'amount' => 1250,
        ]);
        $vendorInvoice = VendorInvoice::factory()->create([
            'amount' => 2000,
            'amount_without_vat' => 1800,
            'vat' => 200,
            'currency' => 'RON',
            'month' => '2026-10-01',
        ]);
        $balance = FinancialAccountBalance::factory()->create([
            'financial_account_id' => $account->id,
            'current_balance' => 9000,
            'currency' => 'EUR',
        ]);

        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'amount' => 1250, 'amount_decimal' => null, 'currency_code' => null]);
        $this->assertDatabaseHas('customer_invoices', ['id' => $invoice->id, 'amount_without_vat' => 1250, 'vat' => 0, 'amount_without_vat_decimal' => null, 'vat_decimal' => null, 'currency_code' => null]);
        $this->assertDatabaseHas('vendor_invoices', ['id' => $vendorInvoice->id, 'amount' => 2000, 'amount_without_vat' => 1800, 'vat' => 200, 'amount_decimal' => null, 'amount_without_vat_decimal' => null, 'vat_decimal' => null, 'currency_code' => null]);
        $this->assertDatabaseHas('taxes', ['id' => $tax->id, 'amount' => 75, 'amount_decimal' => null, 'currency_code' => null]);
        $this->assertDatabaseHas('financial_account_balances', ['id' => $balance->id, 'current_balance' => 9000, 'current_balance_decimal' => null, 'currency_code' => null]);
    }

    public function test_product_cost_resource_and_revenue_canonical_columns_do_not_change_legacy_amounts(): void
    {
        $invoice = CustomerInvoice::factory()->create(['month' => '2026-10-01']);
        $vendorInvoice = VendorInvoice::factory()->create(['month' => '2026-10-01']);
        $resource = ProductResource::factory()->create([
            'amount' => 1250,
            'month' => '2026-10-01',
            'resourceable_type' => 'vendor_invoice',
            'resourceable_id' => $vendorInvoice->id,
        ]);
        $productId = DB::table('products')->insertGetId([
            'title' => 'Test product',
            'status' => 'active',
            'productable_type' => 'course',
            'productable_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $cost = ProductCost::factory()->create([
            'product_id' => $productId,
            'product_resource_id' => $resource->id,
            'month' => '2026-10-01',
            'resource_amount' => 1250,
            'allocated_amount' => 625,
        ]);
        $revenue = ProductRevenue::factory()->create([
            'product_id' => $productId,
            'customer_invoice_id' => $invoice->id,
            'month' => '2026-10-01',
            'amount' => 3000,
        ]);

        $this->assertDatabaseHas('product_resources', ['id' => $resource->id, 'amount' => 1250, 'amount_decimal' => null, 'currency_code' => null]);
        $this->assertDatabaseHas('product_costs', ['id' => $cost->id, 'resource_amount' => 1250, 'allocated_amount' => 625, 'resource_amount_decimal' => null, 'allocated_amount_decimal' => null, 'currency_code' => null]);
        $this->assertDatabaseHas('product_revenues', ['id' => $revenue->id, 'amount' => 3000, 'amount_decimal' => null, 'currency_code' => null]);
    }

    public function test_models_round_trip_canonical_values_to_two_decimal_places(): void
    {
        $account = FinancialAccount::factory()->create(['currency' => 'RON']);
        $invoice = CustomerInvoice::factory()->create(['month' => '2026-10-01']);
        $tax = Tax::factory()->create();
        $transaction = Transaction::factory()->create([
            'financial_account_id' => $account->id,
            'customer_invoice_id' => $invoice->id,
            'tax_id' => $tax->id,
            'vendor_invoice_id' => null,
            'amount_decimal' => '123.45',
            'currency_code' => 'RON',
        ]);

        $this->assertSame('123.45', $transaction->fresh()->amount_decimal);
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'amount_decimal' => '123.45',
            'currency_code' => 'RON',
        ]);
    }

    public function test_rollback_refuses_to_drop_populated_canonical_values(): void
    {
        $account = FinancialAccount::factory()->create();
        $invoice = CustomerInvoice::factory()->create(['month' => '2026-10-01']);
        $tax = Tax::factory()->create();

        DB::table('transactions')->insert([
            'financial_account_id' => $account->id,
            'customer_invoice_id' => $invoice->id,
            'tax_id' => $tax->id,
            'transaction_type' => 'Other',
            'amount' => 10,
            'amount_decimal' => '10.25',
            'currency_code' => 'RON',
            'payment_method' => 'test',
            'timestamp' => '2026-10-24 12:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_10_24_000000_add_canonical_financial_amount_columns.php');

        try {
            $migration->down();
            $this->fail('Expected rollback to refuse dropping populated canonical values.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('transactions.amount_decimal', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasColumn('transactions', 'amount_decimal'));
        $this->assertDatabaseHas('transactions', ['amount_decimal' => '10.25', 'currency_code' => 'RON']);
    }
}
