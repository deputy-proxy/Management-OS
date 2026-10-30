<?php

namespace Tests\Feature;

use App\Models\FinancialAccount;
use App\Models\IssuedInvoice;
use App\Models\LegalEntity;
use App\Models\Transaction;
use App\Models\VendorInvoice;
use App\Services\ConsolidatedFinancialReportData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsolidatedFinancialReportDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_issued_invoices_and_corrections_are_grouped_separately_without_netted_totals(): void
    {
        $entity = LegalEntity::query()->create([
            'legal_name' => 'Synthetic Issuer SRL',
            'country_code' => 'RO',
            'tax_identifier_type' => 'CUI',
            'tax_identifier' => 'TEST-REPORT-349',
        ]);

        IssuedInvoice::query()->create([
            'legal_entity_id' => $entity->id,
            'document_type' => IssuedInvoice::TYPE_INVOICE,
            'document_status' => IssuedInvoice::STATUS_ISSUED,
            'issue_date' => '2026-10-10',
            'currency_code' => 'RON',
            'gross_total' => '120.00',
            'invoice_number' => 'INV-349-1',
            'issued_snapshot_hash' => str_repeat('a', 64),
        ]);

        IssuedInvoice::query()->create([
            'legal_entity_id' => $entity->id,
            'document_type' => IssuedInvoice::TYPE_CREDIT_NOTE,
            'document_status' => IssuedInvoice::STATUS_ISSUED,
            'issue_date' => '2026-10-11',
            'currency_code' => 'RON',
            'gross_total' => '20.00',
            'invoice_number' => 'CN-349-1',
            'corrects_issued_invoice_id' => 1,
            'issued_snapshot_hash' => str_repeat('b', 64),
        ]);

        $report = app(ConsolidatedFinancialReportData::class)->report(
            CarbonImmutable::parse('2026-10-01', 'Europe/Bucharest'),
            CarbonImmutable::parse('2026-10-31', 'Europe/Bucharest'),
            null,
            'customer_documents',
        );

        $this->assertCount(2, $report['groups']);
        $this->assertSame(['credit_note', 'invoice'], collect($report['groups'])->pluck('category')->sort()->values()->all());
        $this->assertSame('120.00', collect($report['groups'])->firstWhere('category', 'invoice')['amount']);
        $this->assertSame('20.00', collect($report['groups'])->firstWhere('category', 'credit_note')['amount']);
        $this->assertSame(2, $report['totals']['included']);
        $this->assertSame('Europe/Bucharest', $report['timezone']);
    }

    public function test_missing_issued_document_amount_or_currency_is_unknown_not_zero(): void
    {
        $entity = LegalEntity::query()->create([
            'legal_name' => 'Synthetic Unknown Issuer SRL',
            'country_code' => 'RO',
            'tax_identifier_type' => 'CUI',
            'tax_identifier' => 'TEST-REPORT-UNKNOWN',
        ]);

        IssuedInvoice::query()->create([
            'legal_entity_id' => $entity->id,
            'document_type' => IssuedInvoice::TYPE_INVOICE,
            'document_status' => IssuedInvoice::STATUS_ISSUED,
            'issue_date' => '2026-10-18',
            'currency_code' => null,
            'gross_total' => null,
            'invoice_number' => 'INV-UNKNOWN',
            'issued_snapshot_hash' => str_repeat('c', 64),
        ]);

        $report = app(ConsolidatedFinancialReportData::class)->report(
            CarbonImmutable::parse('2026-10-01', 'Europe/Bucharest'),
            CarbonImmutable::parse('2026-10-31', 'Europe/Bucharest'),
            null,
            'customer_documents',
        );

        $group = collect($report['groups'])->firstWhere('category', 'invoice');
        $this->assertNotNull($group);
        $this->assertNull($group['amount']);
        $this->assertSame(1, $group['unknown_count']);
        $this->assertContains('missing_or_invalid_currency', $group['reasons']);
    }

    public function test_vendor_invoice_amounts_are_disclosed_as_unknown_even_when_legacy_decimal_columns_are_populated(): void
    {
        VendorInvoice::factory()->create([
            'month' => '2026-10-12',
            'currency_code' => 'RON',
            'amount_decimal' => '450.00',
        ]);

        $report = app(ConsolidatedFinancialReportData::class)->report(
            CarbonImmutable::parse('2026-10-01', 'Europe/Bucharest'),
            CarbonImmutable::parse('2026-10-31', 'Europe/Bucharest'),
            null,
            'vendor_invoices',
        );

        $this->assertCount(1, $report['groups']);
        $this->assertNull($report['groups'][0]['amount']);
        $this->assertSame(1, $report['groups'][0]['excluded_count']);
        $this->assertContains('vendor_payable_total_not_approved', $report['groups'][0]['reasons']);
    }

    public function test_canonical_cash_movements_are_currency_separated_and_unclassified_rows_remain_unknown(): void
    {
        $account = FinancialAccount::factory()->create(['title' => 'Synthetic RON account']);
        Transaction::factory()->create([
            'financial_account_id' => $account->id,
            'vendor_invoice_id' => null,
            'customer_invoice_id' => null,
            'tax_id' => null,
            'transaction_type' => 'Customer',
            'transaction_type_code' => 'money_in',
            'amount_decimal' => '100.25',
            'currency_code' => 'RON',
            'timestamp' => '2026-10-15 12:00:00',
        ]);
        Transaction::factory()->create([
            'financial_account_id' => $account->id,
            'vendor_invoice_id' => null,
            'customer_invoice_id' => null,
            'tax_id' => null,
            'transaction_type' => 'Other',
            'transaction_type_code' => null,
            'amount_decimal' => null,
            'currency_code' => null,
            'timestamp' => '2026-10-16 12:00:00',
        ]);

        $report = app(ConsolidatedFinancialReportData::class)->report(
            CarbonImmutable::parse('2026-10-01', 'Europe/Bucharest'),
            CarbonImmutable::parse('2026-10-31', 'Europe/Bucharest'),
            null,
            'cash_movements',
        );

        $cash = collect($report['groups'])->keyBy('category');
        $this->assertSame('100.25', $cash['customer_money_in']['amount']);
        $this->assertSame(1, $cash['customer_money_in']['included_count']);
        $this->assertNull($cash['unclassified_legacy']['amount']);
        $this->assertSame(1, $cash['unclassified_legacy']['unknown_count']);
    }

    public function test_currency_and_date_filters_do_not_mix_or_include_out_of_period_sources(): void
    {
        $account = FinancialAccount::factory()->create();
        foreach ([
            ['EUR', '12.00', '2026-10-15 12:00:00'],
            ['RON', '14.00', '2026-10-16 12:00:00'],
            ['RON', '99.00', '2026-09-30 23:59:59'],
        ] as [$currency, $amount, $timestamp]) {
            Transaction::factory()->create([
                'financial_account_id' => $account->id,
                'vendor_invoice_id' => null,
                'customer_invoice_id' => null,
                'tax_id' => null,
                'transaction_type' => 'Other',
                'transaction_type_code' => 'money_out',
                'amount_decimal' => $amount,
                'currency_code' => $currency,
                'timestamp' => $timestamp,
            ]);
        }

        $allCurrencies = app(ConsolidatedFinancialReportData::class)->report(
            CarbonImmutable::parse('2026-10-01', 'Europe/Bucharest'),
            CarbonImmutable::parse('2026-10-31', 'Europe/Bucharest'),
            null,
            'cash_movements',
        );

        $this->assertCount(2, $allCurrencies['groups']);
        $this->assertSame(['EUR', 'RON'], collect($allCurrencies['groups'])->pluck('currency')->sort()->values()->all());

        $ronOnly = app(ConsolidatedFinancialReportData::class)->report(
            CarbonImmutable::parse('2026-10-01', 'Europe/Bucharest'),
            CarbonImmutable::parse('2026-10-31', 'Europe/Bucharest'),
            'ron',
            'cash_movements',
        );

        $this->assertCount(1, $ronOnly['groups']);
        $this->assertSame('RON', $ronOnly['groups'][0]['currency']);
        $this->assertSame('14.00', $ronOnly['groups'][0]['amount']);
    }
}
