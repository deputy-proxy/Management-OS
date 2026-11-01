<?php

namespace Tests\Feature;

use App\Models\BudgetActualReportRun;
use App\Models\BudgetVersion;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BudgetActualsComparisonService;
use App\Services\BudgetVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BudgetActualsComparisonServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        foreach (['budget.create', 'budget.update'] as $ability) {
            Gate::define($ability, fn (): bool => true);
        }
    }

    public function test_it_matches_cash_movements_by_explicit_classification_and_keeps_currency_separate(): void
    {
        $version = $this->budget([
            'dimensions' => [],
            'line_items' => [
                ['code' => 'customer_money_in', 'description' => 'Customer receipts', 'amount' => '100.00'],
                ['code' => 'vendor_money_out', 'description' => 'Vendor payments', 'amount' => '40.00'],
            ],
        ]);

        $this->transaction('Customer', 'money_in', '125.00', 'RON', '2027-02-10 12:00:00');
        $this->transaction('Vendor', 'money_out', '25.00', 'RON', '2027-02-11 12:00:00');
        $this->transaction('Customer', 'money_in', '50.00', 'EUR', '2027-02-12 12:00:00');

        $report = app(BudgetActualsComparisonService::class)->compare($version);

        $this->assertSame('classified_transaction_cash_movements_v1', $report['actuals_basis']);
        $this->assertSame('125.00', $report['rows'][0]['actual_amount']);
        $this->assertSame('25.00', $report['rows'][1]['actual_amount']);
        $this->assertSame('25.00', $report['rows'][0]['variance_amount']);
        $this->assertSame('-15.00', $report['rows'][1]['variance_amount']);
        $this->assertCount(1, $report['excluded']);
        $this->assertSame('EUR', $report['excluded'][0]['currency']);
    }

    public function test_missing_and_zero_budget_values_do_not_create_fabricated_variances_or_percentages(): void
    {
        $version = $this->budget([
            'dimensions' => [],
            'line_items' => [
                ['code' => 'customer_money_in', 'description' => 'Zero budget', 'amount' => '0.00'],
                ['code' => 'vendor_money_out', 'description' => 'No actuals', 'amount' => '20.00'],
            ],
        ]);
        $this->transaction('Customer', 'money_in', '10.00', 'RON', '2027-02-10 12:00:00');

        $report = app(BudgetActualsComparisonService::class)->compare($version);

        $this->assertSame('10.00', $report['rows'][0]['variance_amount']);
        $this->assertNull($report['rows'][0]['variance_percent']);
        $this->assertSame('missing_actuals', $report['rows'][1]['status']);
        $this->assertNull($report['rows'][1]['actual_amount']);
        $this->assertNull($report['rows'][1]['variance_amount']);
        $this->assertNull($report['rows'][1]['variance_percent']);
    }

    public function test_dimensioned_budget_lines_remain_unknown_when_transactions_have_no_dimension_contract(): void
    {
        $version = $this->budget([
            'dimensions' => ['department' => 'sales'],
            'line_items' => [[
                'code' => 'customer_money_in',
                'description' => 'Sales receipts',
                'amount' => '100.00',
                'dimensions' => ['department' => 'sales'],
            ]],
        ]);
        $this->transaction('Customer', 'money_in', '125.00', 'RON', '2027-02-10 12:00:00');

        $report = app(BudgetActualsComparisonService::class)->compare($version);

        $this->assertSame('unknown_dimension_attribution', $report['rows'][0]['status']);
        $this->assertNull($report['rows'][0]['actual_amount']);
        $this->assertNull($report['rows'][0]['variance_amount']);
    }

    public function test_saved_report_snapshot_is_unchanged_when_a_source_transaction_is_corrected_later(): void
    {
        $version = $this->budget(['dimensions' => []]);
        $transaction = $this->transaction('Customer', 'money_in', '125.00', 'RON', '2027-02-10 12:00:00');

        $report = app(BudgetActualsComparisonService::class)->compare($version);
        $snapshot = BudgetActualReportRun::query()->findOrFail($report['report_run_id']);
        $transaction->update(['amount_decimal' => '150.00']);

        $this->assertSame('125.00', $snapshot->fresh()->report_snapshot['rows'][0]['actual_amount']);
        $this->assertSame('150.00', app(BudgetActualsComparisonService::class)->compare($version)['rows'][0]['actual_amount']);

        try {
            $snapshot->update(['currency_code' => 'EUR']);
            $this->fail('Saved comparison snapshots must be immutable.');
        } catch (\LogicException) {
            $this->assertSame('RON', $snapshot->fresh()->currency_code);
        }
    }

    public function test_duplicate_budget_codes_are_not_duplicated_across_lines(): void
    {
        $version = $this->budget([
            'dimensions' => [],
            'line_items' => [
                ['code' => 'customer_money_in', 'description' => 'Receipts A', 'amount' => '50.00'],
                ['code' => 'customer_money_in', 'description' => 'Receipts B', 'amount' => '50.00'],
            ],
        ]);
        $this->transaction('Customer', 'money_in', '80.00', 'RON', '2027-02-10 12:00:00');

        $report = app(BudgetActualsComparisonService::class)->compare($version);

        $this->assertSame('ambiguous_budget_code', $report['rows'][0]['status']);
        $this->assertSame('ambiguous_budget_code', $report['rows'][1]['status']);
        $this->assertNull($report['rows'][0]['actual_amount']);
        $this->assertNull($report['rows'][1]['actual_amount']);
        $this->assertCount(1, $report['excluded']);
    }

    public function test_invalid_reporting_period_and_currency_are_rejected(): void
    {
        $version = $this->budget();

        try {
            app(BudgetActualsComparisonService::class)->compare($version, '2026-12-01', '2027-02-01');
            $this->fail('Periods outside the budget version must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('period_end', $exception->errors());
        }

        try {
            app(BudgetActualsComparisonService::class)->compare($version, null, null, 'EUR');
            $this->fail('Currency conversion must not be inferred.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('currency_code', $exception->errors());
        }
    }

    private function budget(array $overrides = []): BudgetVersion
    {
        $payload = array_replace([
            'name' => 'Actuals comparison budget',
            'period_start' => '2027-01-01',
            'period_end' => '2027-12-31',
            'currency_code' => 'RON',
            'dimensions' => [],
            'line_items' => [
                ['code' => 'customer_money_in', 'description' => 'Customer receipts', 'amount' => '100.00'],
            ],
        ], $overrides);

        return app(BudgetVersionService::class)->createBudget($payload)->latestVersion;
    }

    private function transaction(
        string $type,
        string $direction,
        string $amount,
        string $currency,
        string $timestamp,
    ): Transaction {
        return Transaction::factory()->create([
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
}