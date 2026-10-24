<?php

namespace Tests\Feature;

use App\Models\CustomerInvoice;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\EmployeePaySlip;
use App\Models\FinancialAccount;
use App\Models\Tax;
use App\Models\Transaction;
use App\Models\VendorInvoice;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FinanceReconciliationAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_clean_payslip_totals_pass_read_only_audit(): void
    {
        $slip = EmployeePaySlip::factory()->create([
            'manual_amount' => 100.25,
            'amount' => 125.25,
            'status' => 'Open',
            'paid_on' => null,
        ]);

        $this->createLineItem($slip, 25.00);
        DB::table('employee_pay_slips')->where('id', $slip->id)->update(['amount' => 125.25, 'status' => 'Paid', 'paid_on' => '2026-10-18']);

        $this->artisan('finance:reconciliation-audit')
            ->expectsOutputToContain('Read-only finance reconciliation audit. No records will be changed.')
            ->expectsOutputToContain('No configured finance reconciliation findings detected.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('employee_pay_slips', ['id' => $slip->id, 'amount' => '125.25']);
    }

    public function test_audit_reports_total_mismatch_and_paid_slip_without_payment_date_without_mutating_data(): void
    {
        $slip = EmployeePaySlip::factory()->create([
            'manual_amount' => 100,
            'amount' => 999,
            'status' => 'Open',
            'paid_on' => null,
        ]);

        $this->createLineItem($slip, 25);
        DB::table('employee_pay_slips')->where('id', $slip->id)->update(['amount' => 999, 'status' => 'Paid']);

        $exitCode = Artisan::call('finance:reconciliation-audit');
        $output = Artisan::output();
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('payslip total mismatch', $output);
        $this->assertStringContainsString('paid payslip missing payment date: 1', $output);
        $this->assertStringContainsString('No data was modified.', $output);
        $this->assertStringNotContainsString('999', $output);

        $this->assertDatabaseHas('employee_pay_slips', [
            'id' => $slip->id,
            'amount' => '999.00',
            'paid_on' => null,
        ]);
    }

    public function test_audit_reports_duplicate_employee_periods_including_soft_deleted_history(): void
    {
        $employee = Employee::factory()->create();
        $slip = EmployeePaySlip::factory()->create(['employee_id' => $employee->id, 'month' => '2026-10-01']);
        Schema::table('employee_pay_slips', function (Blueprint $table): void {
            $table->dropUnique('employee_pay_slips_employee_month_unique');
        });
        DB::table('employee_pay_slips')->insert([
            'employee_id' => $employee->id,
            'month' => DB::table('employee_pay_slips')->where('id', $slip->id)->value('month'),
            'items' => '[]',
            'amount' => 0,
            'manual_amount' => 0,
            'status' => 'Open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('finance:reconciliation-audit')
            ->expectsOutputToContain('duplicate payslip period')
            ->assertExitCode(1);

        $this->assertDatabaseCount('employee_pay_slips', 2);
    }

    public function test_audit_reports_missing_source_records_and_unknown_source_types(): void
    {
        $slip = EmployeePaySlip::factory()->create(['manual_amount' => 0, 'amount' => 0]);
        DB::table('employee_pay_slip_items')->insert([
            'employee_pay_slip_id' => $slip->id,
            'item_type' => 'session',
            'source_type' => 'session',
            'source_id' => 987654,
            'period_key' => $slip->month->format('Y-m'),
            'source_key' => 'missing-session-source',
            'occurred_on' => $slip->month->toDateString(),
            'description' => 'Synthetic missing source',
            'quantity' => 1,
            'unit' => 'hour',
            'unit_rate' => 10,
            'amount' => 10,
            'currency' => 'RON',
            'calculation_snapshot' => json_encode(['test' => true], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('employee_pay_slip_items')->insert([
            'employee_pay_slip_id' => $slip->id,
            'item_type' => 'legacy_unknown',
            'source_type' => 'legacy_unknown',
            'source_id' => 987655,
            'period_key' => $slip->month->format('Y-m'),
            'source_key' => 'unknown-source-type',
            'occurred_on' => $slip->month->toDateString(),
            'description' => 'Synthetic unknown source type',
            'quantity' => 1,
            'unit' => 'unit',
            'unit_rate' => 0,
            'amount' => 0,
            'currency' => 'RON',
            'calculation_snapshot' => json_encode(['test' => true], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('finance:reconciliation-audit')
            ->expectsOutputToContain('missing payslip source record: source_type=session')
            ->expectsOutputToContain('unknown payslip source type: source_type=legacy_unknown')
            ->assertExitCode(1);

        $this->assertDatabaseCount('employee_pay_slip_items', 2);
    }

    public function test_audit_reports_transactions_linked_to_both_customer_and_vendor_invoices(): void
    {
        $customerInvoice = CustomerInvoice::factory()->create(['month' => '2026-10-01']);
        $vendorInvoice = VendorInvoice::factory()->create(['month' => '2026-10-01']);
        $account = FinancialAccount::factory()->create();
        $tax = Tax::factory()->create(['month' => '2026-10']);
        $transaction = Transaction::factory()->create([
            'customer_invoice_id' => $customerInvoice->id,
            'vendor_invoice_id' => $vendorInvoice->id,
            'financial_account_id' => $account->id,
            'tax_id' => $tax->id,
        ]);

        $this->artisan('finance:reconciliation-audit')
            ->expectsOutputToContain('transaction linked to both customer and vendor invoices')
            ->expectsOutputToContain('Invoice-linked transaction summaries (informational only')
            ->assertExitCode(1);

        $this->assertDatabaseHas('transactions', ['id' => $transaction->id]);
    }

    public function test_financial_audit_detects_precision_currency_and_account_mismatches_without_printing_amounts(): void
    {
        $account = FinancialAccount::factory()->create(['currency' => 'RON']);
        $invoice = CustomerInvoice::factory()->create(['month' => '2026-10-01']);
        $tax = Tax::factory()->create(['month' => '2026-10']);
        $precisionMismatch = Transaction::factory()->create([
            'financial_account_id' => $account->id,
            'customer_invoice_id' => $invoice->id,
            'vendor_invoice_id' => null,
            'tax_id' => $tax->id,
            'amount' => 123456,
            'amount_decimal' => '123.45',
            'currency_code' => 'EUR',
            'transaction_type' => 'income',
            'transaction_type_code' => 'money_in',
        ]);
        $invalidCurrency = Transaction::factory()->create([
            'financial_account_id' => $account->id,
            'customer_invoice_id' => $invoice->id,
            'vendor_invoice_id' => null,
            'tax_id' => $tax->id,
            'amount' => 1000,
            'amount_decimal' => '10.00',
            'currency_code' => 'ron',
            'transaction_type' => 'income',
            'transaction_type_code' => 'money_in',
        ]);
        DB::table('transactions')->where('id', $precisionMismatch->id)->update(['amount_decimal' => '123.456']);
        DB::table('transactions')->insert([
            'financial_account_id' => $account->id,
            'customer_invoice_id' => $invoice->id,
            'vendor_invoice_id' => null,
            'tax_id' => $tax->id,
            'amount' => 500,
            'amount_decimal' => '5.00',
            'currency_code' => null,
            'transaction_type' => 'income',
            'transaction_type_code' => 'money_in',
            'payment_method' => 'test',
            'timestamp' => '2026-10-24 12:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $exitCode = Artisan::call('finance:reconciliation-audit');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('invalid canonical decimal precision: transactions.amount_decimal', $output);
        $this->assertStringContainsString('transaction/account currency mismatch', $output);
        $this->assertStringContainsString('canonical amount without currency: transactions.amount_decimal', $output);
        $this->assertStringContainsString('invalid canonical currency-code format: transactions.currency_code', $output);
        $this->assertStringNotContainsString('123456', $output);
        $this->assertStringNotContainsString('123.456', $output);
        $this->assertStringNotContainsString('10.00', $output);
        $this->assertDatabaseHas('transactions', ['id' => $invalidCurrency->id, 'currency_code' => 'ron']);
    }

    public function test_invoice_total_audit_checks_only_the_configured_approved_formula(): void
    {
        config(['financial-normalization.tables.vendor_invoices.invoice_semantics.rule' => 'vendor_total_equals_components']);
        $invoice = VendorInvoice::factory()->create([
            'month' => '2026-10-01',
            'amount' => 1500,
            'amount_without_vat' => 1000,
            'vat' => 200,
        ]);

        $exitCode = Artisan::call('finance:reconciliation-audit');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('vendor invoice total/component mismatch or malformed amount', $output);
        $this->assertStringNotContainsString('1500', $output);
        $this->assertStringNotContainsString('1000', $output);
        $this->assertDatabaseHas('vendor_invoices', ['id' => $invoice->id, 'amount' => 1500, 'amount_without_vat' => 1000, 'vat' => 200]);
    }

    public function test_audit_passes_reconciled_synthetic_financial_records_with_approved_rules(): void
    {
        config([
            'financial-normalization.tables.vendor_invoices.invoice_semantics.rule' => 'vendor_total_equals_components',
            'financial-normalization.tables.customer_invoices.invoice_semantics.rule' => 'customer_vat_zero',
        ]);
        $customerInvoice = CustomerInvoice::factory()->create([
            'month' => '2026-10-01',
            'amount_without_vat' => 1000,
            'vat' => 0,
            'amount_without_vat_decimal' => '1000.00',
            'vat_decimal' => '0.00',
            'currency_code' => 'RON',
            'status_code' => 'open',
        ]);
        $tax = Tax::factory()->create(['month' => '2026-10', 'amount_decimal' => '0.00', 'currency_code' => 'RON', 'status_code' => 'open']);
        $account = FinancialAccount::factory()->create(['currency' => 'RON']);
        $invoice = VendorInvoice::factory()->create([
            'month' => '2026-10-01',
            'amount' => 1200,
            'amount_without_vat' => 1000,
            'vat' => 200,
            'amount_decimal' => '1200.00',
            'amount_without_vat_decimal' => '1000.00',
            'vat_decimal' => '200.00',
            'currency' => 'RON',
            'currency_code' => 'RON',
            'status_code' => 'open',
        ]);
        DB::table('transactions')->insert([
            'financial_account_id' => $account->id,
            'customer_invoice_id' => $customerInvoice->id,
            'vendor_invoice_id' => null,
            'tax_id' => $tax->id,
            'amount' => 1000,
            'amount_decimal' => '1000.00',
            'currency_code' => 'RON',
            'transaction_type' => 'income',
            'transaction_type_code' => 'money_in',
            'payment_method' => 'test',
            'timestamp' => '2026-10-24 12:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $exitCode = Artisan::call('finance:reconciliation-audit');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode, $output);
        $this->assertStringNotContainsString('1200', $output);
    }

    public function test_audit_detects_disagreement_with_an_explicitly_approved_amount_mapping(): void
    {
        $account = FinancialAccount::factory()->create(['currency' => 'RON']);
        $invoice = CustomerInvoice::factory()->create(['month' => '2026-10-01']);
        $tax = Tax::factory()->create(['month' => '2026-10']);
        $transaction = Transaction::factory()->create([
            'financial_account_id' => $account->id,
            'customer_invoice_id' => $invoice->id,
            'vendor_invoice_id' => null,
            'tax_id' => $tax->id,
            'amount' => 12345,
            'amount_decimal' => '999.99',
            'currency_code' => 'RON',
            'transaction_type' => 'income',
            'transaction_type_code' => 'money_in',
        ]);
        config(['financial-normalization.tables.transactions.amounts.0.unit' => 'minor']);

        $exitCode = Artisan::call('finance:reconciliation-audit');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('canonical amount disagrees with approved source mapping: transactions.amount_decimal', $output);
        $this->assertStringNotContainsString('12345', $output);
        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'amount' => 12345, 'amount_decimal' => '999.99']);
    }

    public function test_invoice_semantics_without_an_approved_rule_are_reported_as_unresolved(): void
    {
        $invoice = VendorInvoice::factory()->create(['month' => '2026-10-01', 'amount' => 1200]);

        $exitCode = Artisan::call('finance:reconciliation-audit');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('invoice semantics unresolved: vendor_invoices', $output);
        $this->assertStringContainsString('vendor_invoices.id samples='.$invoice->id, $output);
        $this->assertStringNotContainsString('1200', $output);
    }

    public function test_audit_reports_unresolved_canonical_amount_currency_and_classification(): void
    {
        $account = FinancialAccount::factory()->create(['currency' => 'RON']);
        $invoice = CustomerInvoice::factory()->create(['month' => '2026-10-01']);
        $tax = Tax::factory()->create(['month' => '2026-10']);
        $transaction = Transaction::factory()->create([
            'financial_account_id' => $account->id,
            'customer_invoice_id' => $invoice->id,
            'vendor_invoice_id' => null,
            'tax_id' => $tax->id,
            'amount' => 987654,
            'amount_decimal' => null,
            'currency_code' => null,
            'transaction_type' => 'income',
            'transaction_type_code' => null,
        ]);
        $customerInvoice = CustomerInvoice::factory()->create([
            'month' => '2026-10-01',
            'amount_without_vat' => 4321,
            'amount_without_vat_decimal' => null,
            'currency_code' => null,
            'status' => 'Open',
            'status_code' => null,
        ]);

        $exitCode = Artisan::call('finance:reconciliation-audit');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('canonical amount unresolved: transactions.amount_decimal', $output);
        $this->assertStringContainsString('canonical amount unresolved: customer_invoices.amount_without_vat_decimal', $output);
        $this->assertStringContainsString('legacy classification unresolved: transactions.transaction_type_code', $output);
        $this->assertStringContainsString('legacy classification unresolved: customer_invoices.status_code', $output);
        $this->assertStringNotContainsString('987654', $output);
        $this->assertStringNotContainsString('4321', $output);
        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'amount_decimal' => null, 'currency_code' => null, 'transaction_type_code' => null]);
        $this->assertDatabaseHas('customer_invoices', ['id' => $customerInvoice->id, 'amount_without_vat_decimal' => null, 'currency_code' => null, 'status_code' => null]);
    }

    private function createLineItem(EmployeePaySlip $slip, float $amount): void
    {
        $contract = EmployeeContract::factory()->create(['employee_id' => $slip->employee_id]);

        DB::table('employee_pay_slip_items')->insert([
            'employee_pay_slip_id' => $slip->id,
            'item_type' => 'session',
            'source_type' => 'employee_contract',
            'source_id' => $contract->id,
            'period_key' => $slip->month->format('Y-m'),
            'source_key' => 'test-source-'.$slip->id,
            'occurred_on' => $slip->month->toDateString(),
            'description' => 'Synthetic reconciliation test row',
            'quantity' => 1,
            'unit' => 'hour',
            'unit_rate' => $amount,
            'amount' => $amount,
            'currency' => 'RON',
            'calculation_snapshot' => json_encode(['test' => true], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
