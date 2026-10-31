<?php

namespace Tests\Feature;

use App\Models\FinancialAccount;
use App\Models\IssuedInvoice;
use App\Models\LegalEntity;
use App\Models\Transaction;
use App\Models\VendorInvoice;
use App\Services\ConsolidatedFinancialReportData;
use App\Services\FinancialOverviewData;
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

    public function test_cash_movement_filters_apply_account_direction_and_category_together(): void
    {
        $ronAccount = FinancialAccount::factory()->create(['title' => 'Synthetic RON operating']);
        $eurAccount = FinancialAccount::factory()->create(['title' => 'Synthetic EUR reserve']);
        $base = [
            'vendor_invoice_id' => null,
            'customer_invoice_id' => null,
            'tax_id' => null,
            'timestamp' => '2026-10-15 12:00:00',
            'currency_code' => 'RON',
        ];

        Transaction::factory()->create($base + [
            'financial_account_id' => $ronAccount->id,
            'transaction_type' => 'Customer',
            'transaction_type_code' => 'money_in',
            'amount_decimal' => '100.00',
        ]);
        Transaction::factory()->create($base + [
            'financial_account_id' => $ronAccount->id,
            'transaction_type' => 'Vendor',
            'transaction_type_code' => 'money_out',
            'amount_decimal' => '25.00',
        ]);
        Transaction::factory()->create($base + [
            'financial_account_id' => $eurAccount->id,
            'transaction_type' => 'Customer',
            'transaction_type_code' => 'money_in',
            'amount_decimal' => '50.00',
        ]);

        $report = app(ConsolidatedFinancialReportData::class)->report(
            CarbonImmutable::parse('2026-10-01', 'Europe/Bucharest'),
            CarbonImmutable::parse('2026-10-31', 'Europe/Bucharest'),
            null,
            'cash_movements',
            [
                'account_id' => (string) $ronAccount->id,
                'direction' => 'money_in',
                'category' => 'customer_money_in',
            ],
        );

        $this->assertCount(1, $report['records']);
        $this->assertSame($ronAccount->id, $report['records'][0]['account_id']);
        $this->assertSame('customer_money_in', $report['records'][0]['category']);
        $this->assertSame('100.00', $report['records'][0]['amount']);
        $this->assertSame(1, $report['totals']['included']);
        $this->assertSame('Synthetic RON operating', collect($report['cash_filter_options']['accounts'])->firstWhere('id', $ronAccount->id)['title']);
    }

    public function test_identical_transactions_are_flagged_as_potential_duplicates_but_both_remain_counted(): void
    {
        $account = FinancialAccount::factory()->create();
        $attributes = [
            'financial_account_id' => $account->id,
            'vendor_invoice_id' => null,
            'customer_invoice_id' => null,
            'tax_id' => null,
            'transaction_type' => 'Other',
            'transaction_type_code' => 'money_in',
            'amount_decimal' => '12.50',
            'currency_code' => 'RON',
            'payment_method' => 'Bank transfer',
            'timestamp' => '2026-10-15 12:00:00',
        ];
        Transaction::factory()->create($attributes);
        Transaction::factory()->create($attributes);

        $report = app(ConsolidatedFinancialReportData::class)->report(
            CarbonImmutable::parse('2026-10-01', 'Europe/Bucharest'),
            CarbonImmutable::parse('2026-10-31', 'Europe/Bucharest'),
            null,
            'cash_movements',
        );

        $this->assertCount(2, $report['records']);
        $this->assertSame(['potential_duplicate', 'potential_duplicate'], collect($report['records'])->pluck('duplicate_status')->all());
        $this->assertSame([2, 2], collect($report['records'])->pluck('duplicate_count')->all());
        $this->assertSame(2, $report['totals']['included']);
        $this->assertSame('25.00', $report['groups'][0]['amount']);
    }

    public function test_transfer_and_reversal_semantics_are_not_inferred_or_netted(): void
    {
        $outAccount = FinancialAccount::factory()->create(['title' => 'Transfer source']);
        $inAccount = FinancialAccount::factory()->create(['title' => 'Transfer destination']);
        $common = [
            'vendor_invoice_id' => null,
            'customer_invoice_id' => null,
            'tax_id' => null,
            'amount_decimal' => '40.00',
            'currency_code' => 'RON',
            'timestamp' => '2026-10-15 12:00:00',
        ];
        Transaction::factory()->create($common + [
            'financial_account_id' => $outAccount->id,
            'transaction_type' => 'Other',
            'transaction_type_code' => 'money_out',
            'payment_method' => 'Transfer',
        ]);
        Transaction::factory()->create($common + [
            'financial_account_id' => $inAccount->id,
            'transaction_type' => 'Other',
            'transaction_type_code' => 'money_in',
            'payment_method' => 'Transfer',
        ]);
        Transaction::factory()->create($common + [
            'financial_account_id' => $inAccount->id,
            'transaction_type' => 'Other',
            'transaction_type_code' => 'money_in',
            'payment_method' => 'Reversal',
            'timestamp' => '2026-10-16 12:00:00',
        ]);

        $report = app(ConsolidatedFinancialReportData::class)->report(
            CarbonImmutable::parse('2026-10-01', 'Europe/Bucharest'),
            CarbonImmutable::parse('2026-10-31', 'Europe/Bucharest'),
            null,
            'cash_movements',
        );

        $this->assertCount(3, $report['records']);
        $this->assertSame(3, $report['totals']['included']);
        $this->assertSame(['not_modelled', 'not_modelled', 'not_modelled'], collect($report['records'])->pluck('special_event_status')->all());
        $this->assertSame(['other_money_out', 'other_money_in', 'other_money_in'], collect($report['records'])->pluck('category')->all());
        $this->assertSame('40.00', collect($report['groups'])->firstWhere('entity', 'Transfer source')['amount']);
        $this->assertSame('80.00', collect($report['groups'])->firstWhere('entity', 'Transfer destination')['amount']);
    }

    public function test_unknown_transaction_classifications_remain_visible_and_are_not_summed(): void
    {
        $account = FinancialAccount::factory()->create();
        Transaction::factory()->create([
            'financial_account_id' => $account->id,
            'vendor_invoice_id' => null,
            'customer_invoice_id' => null,
            'tax_id' => null,
            'transaction_type' => 'Unmapped legacy value',
            'transaction_type_code' => null,
            'amount_decimal' => '99.00',
            'currency_code' => 'RON',
            'timestamp' => '2026-10-15 12:00:00',
        ]);

        $report = app(ConsolidatedFinancialReportData::class)->report(
            CarbonImmutable::parse('2026-10-01', 'Europe/Bucharest'),
            CarbonImmutable::parse('2026-10-31', 'Europe/Bucharest'),
            null,
            'cash_movements',
            ['direction' => 'unknown'],
        );

        $this->assertCount(1, $report['records']);
        $this->assertSame('unclassified_legacy', $report['records'][0]['category']);
        $this->assertSame('unknown', $report['records'][0]['status']);
        $this->assertNull($report['records'][0]['amount']);
        $this->assertSame(1, $report['totals']['unknown']);
    }

    public function test_unclassified_business_type_still_matches_canonical_cash_inclusion_fields(): void
    {
        $account = FinancialAccount::factory()->create();
        Transaction::factory()->create([
            'financial_account_id' => $account->id,
            'vendor_invoice_id' => null,
            'customer_invoice_id' => null,
            'tax_id' => null,
            'transaction_type' => 'Legacy category not mapped',
            'transaction_type_code' => 'money_in',
            'amount_decimal' => '99.00',
            'currency_code' => 'RON',
            'timestamp' => '2026-10-15 12:00:00',
        ]);

        $report = app(ConsolidatedFinancialReportData::class)->report(
            CarbonImmutable::parse('2026-10-01', 'Europe/Bucharest'),
            CarbonImmutable::parse('2026-10-31', 'Europe/Bucharest'),
            null,
            'cash_movements',
            ['category' => 'unclassified_legacy'],
        );

        $this->assertCount(1, $report['records']);
        $this->assertSame('included', $report['records'][0]['status']);
        $this->assertSame('unclassified', $report['records'][0]['classification_status']);
        $this->assertSame('unclassified_transaction_type', $report['records'][0]['classification_warning']);
        $this->assertSame('99.00', $report['records'][0]['amount']);
        $this->assertSame(1, $report['totals']['included']);
        $this->assertSame(0, $report['totals']['unknown']);
    }

    public function test_cash_report_totals_match_the_canonical_financial_overview_source(): void
    {
        $account = FinancialAccount::factory()->create();
        foreach ([
            ['Customer', 'money_in', '100.25', 'RON', '2026-10-10 10:00:00'],
            ['Vendor', 'money_out', '40.00', 'RON', '2026-10-11 10:00:00'],
            ['Unmapped', 'money_in', '12.50', 'EUR', '2026-10-12 10:00:00'],
            ['Other', null, '9.00', 'RON', '2026-10-13 10:00:00'],
            ['Other', 'money_in', null, 'RON', '2026-10-14 10:00:00'],
            ['Other', 'money_out', '4.00', null, '2026-10-15 10:00:00'],
        ] as [$type, $direction, $amount, $currency, $timestamp]) {
            Transaction::factory()->create([
                'financial_account_id' => $account->id,
                'vendor_invoice_id' => null,
                'customer_invoice_id' => null,
                'tax_id' => null,
                'transaction_type' => $type,
                'transaction_type_code' => $direction,
                'amount_decimal' => $amount,
                'currency_code' => $currency,
                'timestamp' => $timestamp,
            ]);
        }

        $start = CarbonImmutable::parse('2026-10-01', 'Europe/Bucharest');
        $end = CarbonImmutable::parse('2026-10-31', 'Europe/Bucharest');
        $report = app(ConsolidatedFinancialReportData::class)->report($start, $end, null, 'cash_movements');
        $overview = app(FinancialOverviewData::class)->summary($start->addDays(10));

        $actual = collect($report['records'])
            ->where('status', 'included')
            ->groupBy(fn (array $record): string => $record['currency'].'|'.$record['direction'])
            ->map(fn ($records): string => $records->reduce(
                fn (string $total, array $record): string => bcadd($total, $record['amount'], 2),
                '0.00',
            ));
        $expected = collect($overview['canonical_cash_movements']['rows'])
            ->mapWithKeys(fn (array $row): array => [$row['currency'].'|'.$row['direction'] => $row['current_amount']]);

        foreach ($expected as $key => $amount) {
            $this->assertSame($amount, $actual->get($key, '0.00'), $key);
        }
        foreach ($actual as $key => $amount) {
            $this->assertSame($amount, $expected->get($key, '0.00'), $key);
        }
    }
}
