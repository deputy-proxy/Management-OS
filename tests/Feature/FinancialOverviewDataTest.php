<?php

namespace Tests\Feature;

use App\Models\CustomerInvoice;
use App\Models\FinancialAccount;
use App\Models\Tax;
use App\Models\Transaction;
use App\Models\VendorInvoice;
use App\Services\FinancialOverviewData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialOverviewDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_compares_transaction_totals_by_currency_and_type_without_mixing_currencies(): void
    {
        $now = CarbonImmutable::parse('2026-10-17 12:00:00', config('app.timezone'));
        $accountEur = FinancialAccount::factory()->create(['currency' => 'EUR']);
        $accountRon = FinancialAccount::factory()->create(['currency' => 'RON']);
        $invoice = CustomerInvoice::factory()->create(['month' => '2026-10-01']);
        $tax = Tax::factory()->create();

        Transaction::factory()->create([
            'financial_account_id' => $accountEur->id,
            'customer_invoice_id' => $invoice->id,
            'tax_id' => $tax->id,
            'vendor_invoice_id' => null,
            'transaction_type' => 'Customer',
            'amount' => 1200,
            'timestamp' => '2026-10-10 10:00:00',
        ]);
        Transaction::factory()->create([
            'financial_account_id' => $accountEur->id,
            'customer_invoice_id' => $invoice->id,
            'tax_id' => $tax->id,
            'vendor_invoice_id' => null,
            'transaction_type' => 'Customer',
            'amount' => 800,
            'timestamp' => '2026-09-10 10:00:00',
        ]);
        Transaction::factory()->create([
            'financial_account_id' => $accountRon->id,
            'customer_invoice_id' => $invoice->id,
            'tax_id' => $tax->id,
            'vendor_invoice_id' => null,
            'transaction_type' => 'Customer',
            'amount' => 500,
            'timestamp' => '2026-10-11 10:00:00',
        ]);
        Transaction::factory()->create([
            'financial_account_id' => $accountEur->id,
            'customer_invoice_id' => $invoice->id,
            'tax_id' => $tax->id,
            'vendor_invoice_id' => null,
            'transaction_type' => 'Vendor',
            'amount' => 200,
            'timestamp' => '2026-10-12 10:00:00',
        ]);

        $summary = app(FinancialOverviewData::class)->summary($now);
        $rows = collect($summary['transactions'])->keyBy(fn (array $row): string => $row['currency'].'|'.$row['type']);

        $this->assertSame('October 2026', $summary['period']);
        $this->assertSame('September 2026', $summary['previous_period']);
        $this->assertSame(1200, $rows['EUR|Customer']['current_total']);
        $this->assertSame(800, $rows['EUR|Customer']['previous_total']);
        $this->assertSame(1, $rows['EUR|Customer']['current_count']);
        $this->assertSame(500, $rows['RON|Customer']['current_total']);
        $this->assertSame(200, $rows['EUR|Vendor']['current_total']);
    }

    public function test_invoice_status_counts_use_billing_month_and_exclude_soft_deleted_records(): void
    {
        $now = CarbonImmutable::parse('2026-10-17 12:00:00', config('app.timezone'));
        CustomerInvoice::factory()->create(['month' => '2026-10-01', 'status' => 'Sent']);
        CustomerInvoice::factory()->create(['month' => '2026-10-01', 'status' => 'Sent'])->delete();
        CustomerInvoice::factory()->create(['month' => '2026-09-01', 'status' => 'Paid']);
        VendorInvoice::factory()->create(['month' => '2026-10-05', 'status' => 'Pending', 'currency' => 'EUR']);
        VendorInvoice::factory()->create(['month' => '2026-10-06', 'status' => 'Pending', 'currency' => 'RON']);

        $summary = app(FinancialOverviewData::class)->summary($now);

        $this->assertSame([['status' => 'Sent', 'count' => 1]], $summary['customer_invoice_statuses']);
        $this->assertSame([
            ['status' => 'Pending', 'currency' => 'EUR', 'count' => 1],
            ['status' => 'Pending', 'currency' => 'RON', 'count' => 1],
        ], $summary['vendor_invoice_statuses']);
    }

    public function test_summary_handles_empty_data(): void
    {
        $summary = app(FinancialOverviewData::class)->summary(CarbonImmutable::parse('2026-10-17', config('app.timezone')));

        $this->assertSame([], $summary['transactions']);
        $this->assertSame([], $summary['customer_invoice_statuses']);
        $this->assertSame([], $summary['vendor_invoice_statuses']);
    }
}