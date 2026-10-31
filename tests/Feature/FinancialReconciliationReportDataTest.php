<?php

namespace Tests\Feature;

use App\Filament\Pages\FinancialReconciliationDashboard;
use App\Models\BankStatementImportBatch;
use App\Models\BankStatementImportItem;
use App\Models\FinancialAccount;
use App\Models\PaymentAllocation;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BankStatements\BankStatementMatchingService;
use App\Services\Finance\FinancialReconciliationReportData;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class FinancialReconciliationReportDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        Gate::before(static fn ($user, string $ability): ?bool => $ability === 'transaction.update' ? true : null);
    }

    public function test_report_finds_exact_and_near_date_candidates_without_mutating_transactions(): void
    {
        [$account, $item, $actor] = $this->completedStatementItem();
        $exact = $this->transaction($account, '2026-10-15 12:00:00', 'money_in', '125.00', 'RON');
        $near = $this->transaction($account, '2026-10-17 12:00:00', 'money_in', '125.00', 'RON');
        PaymentAllocation::query()->create([
            'transaction_id' => $exact->id,
            'allocation_kind' => PaymentAllocation::KIND_FEE,
            'amount_decimal' => '2.00',
            'currency_code' => 'RON',
            'status' => PaymentAllocation::STATUS_REVERSED,
            'reason' => 'Synthetic reversed fee allocation',
            'created_by' => $actor->id,
            'reversed_by' => $actor->id,
            'reversed_at' => now(),
            'reversal_reason' => 'Synthetic reversal',
        ]);

        $report = app(FinancialReconciliationReportData::class)->report(
            CarbonImmutable::parse('2026-10-01', 'Europe/Bucharest'),
            CarbonImmutable::parse('2026-10-31', 'Europe/Bucharest'),
        );

        $row = collect($report['rows'])->firstWhere('id', $item->id);
        $this->assertSame('unreviewed', $row['status']);
        $this->assertSame('exact_candidate', $row['candidate_status']);
        $this->assertSame([$exact->id], collect($row['candidates'])->pluck('transaction_id')->all());
        $this->assertSame(1, $row['candidates'][0]['allocation_counts']['reversed']);
        $this->assertSame('125.00', $exact->fresh()->amount_decimal);
        $this->assertSame('RON', $near->fresh()->currency_code);
        $this->assertSame(1, $report['summary']['unreviewed']);
    }

    public function test_multiple_exact_candidates_are_ambiguous_and_currency_and_account_boundaries_are_preserved(): void
    {
        [$account, $item] = $this->completedStatementItem();
        $first = $this->transaction($account, '2026-10-15 09:00:00', 'money_in', '125.00', 'RON');
        $second = $this->transaction($account, '2026-10-15 15:00:00', 'money_in', '125.00', 'RON');
        $this->transaction($account, '2026-10-15 12:00:00', 'money_in', '125.00', 'EUR');
        $otherAccount = FinancialAccount::factory()->create(['currency' => 'EUR']);
        $this->completedStatementItem('2026-10-16', $otherAccount, 'EUR');

        $report = app(FinancialReconciliationReportData::class)->report(
            CarbonImmutable::parse('2026-10-01', 'Europe/Bucharest'),
            CarbonImmutable::parse('2026-10-31', 'Europe/Bucharest'),
            $account->id,
            'ron',
        );
        $row = collect($report['rows'])->firstWhere('id', $item->id);

        $this->assertSame('ambiguous', $row['status']);
        $this->assertSame('ambiguous', $row['candidate_status']);
        $this->assertEqualsCanonicalizing([$first->id, $second->id], collect($row['candidates'])->pluck('transaction_id')->all());
        $this->assertCount(2, $report['accounts']);
        $this->assertSame(['EUR', 'RON'], $report['currencies']);
    }

    public function test_near_date_candidate_is_suggestion_only_and_outside_tolerance_is_not_returned(): void
    {
        [$account, $item] = $this->completedStatementItem();
        $near = $this->transaction($account, '2026-10-18 12:00:00', 'money_in', '125.00', 'RON');
        $this->transaction($account, '2026-10-19 12:00:00', 'money_in', '125.00', 'RON');

        $report = app(FinancialReconciliationReportData::class)->report(
            CarbonImmutable::parse('2026-10-01', 'Europe/Bucharest'),
            CarbonImmutable::parse('2026-10-31', 'Europe/Bucharest'),
        );
        $row = collect($report['rows'])->firstWhere('id', $item->id);

        $this->assertSame('unreviewed', $row['status']);
        $this->assertSame('near_candidate', $row['candidate_status']);
        $this->assertSame([$near->id], collect($row['candidates'])->pluck('transaction_id')->all());
        $this->assertStringContainsString('not an approved automatic matching rule', $row['candidate_rule']);
    }

    public function test_manual_partial_decisions_and_revised_decision_history_are_reported(): void
    {
        [$account, $item, $actor] = $this->completedStatementItem();
        $transaction = $this->transaction($account, '2026-10-15 12:00:00', 'money_in', '60.00', 'RON');
        $matching = app(BankStatementMatchingService::class);
        $matching->recordDecision($item->id, $actor, 'partial_candidate', 'transaction', $transaction->id, 'Possible partial payment');
        $matching->recordDecision($item->id, $actor, 'unmatched', null, null, 'Source evidence did not support a match');

        $report = app(FinancialReconciliationReportData::class)->report(
            CarbonImmutable::parse('2026-10-01', 'Europe/Bucharest'),
            CarbonImmutable::parse('2026-10-31', 'Europe/Bucharest'),
        );
        $row = collect($report['rows'])->firstWhere('id', $item->id);

        $this->assertSame('unmatched', $row['status']);
        $this->assertSame('partial_candidate', $row['candidate_status']);
        $this->assertSame('unmatched', $row['decision']['outcome']);
        $this->assertCount(2, $row['history']);
        $this->assertSame('partial_candidate', $row['history'][0]['outcome']);
        $this->assertSame('unmatched', $row['history'][1]['outcome']);
        $this->assertSame('60.00', $transaction->fresh()->amount_decimal);
        $this->assertSame(1, $report['summary']['unmatched']);
    }

    public function test_date_boundaries_and_decision_filter_are_applied_to_completed_statement_rows(): void
    {
        [$account, $first, $actor] = $this->completedStatementItem('2026-10-01');
        [, $last] = $this->completedStatementItem('2026-10-31', $account);
        [, $outside] = $this->completedStatementItem('2026-11-01', $account);
        app(BankStatementMatchingService::class)->recordDecision($first->id, $actor, 'rejected', null, null, 'Not the same source event');

        $report = app(FinancialReconciliationReportData::class)->report(
            CarbonImmutable::parse('2026-10-01', 'Europe/Bucharest'),
            CarbonImmutable::parse('2026-10-31', 'Europe/Bucharest'),
            null,
            null,
            'rejected',
        );

        $this->assertSame([$first->id], collect($report['rows'])->pluck('id')->all());
        $this->assertNotContains($last->id, collect($report['rows'])->pluck('id')->all());
        $this->assertNotContains($outside->id, collect($report['rows'])->pluck('id')->all());
        $this->assertSame(1, $report['summary']['rejected']);
    }

    public function test_reconciliation_dashboard_uses_a_dedicated_view_permission(): void
    {
        $page = new FinancialReconciliationDashboard;
        $this->assertSame(
            ['financial-reconciliation.view' => __('Allows viewing read-only financial reconciliation diagnostics')],
            $page->defineGates(),
        );

        $this->actingAs(User::factory()->create());
        $allowed = true;
        Gate::before(function ($user, string $ability) use (&$allowed): ?bool {
            return $ability === 'financial-reconciliation.view' ? $allowed : null;
        });
        $this->assertTrue(FinancialReconciliationDashboard::canAccess());
        $allowed = false;
        $this->assertFalse(FinancialReconciliationDashboard::canAccess());
    }

    public function test_dashboard_renders_for_a_user_with_the_dedicated_permission(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $user = User::factory()->create();
        $roleClass = config('hexa.models.role');
        $role = $roleClass::create([
            'name' => 'Financial reconciliation viewer '.uniqid(),
            'created_by_name' => 'Test',
            'access' => ['Finance' => ['financial-reconciliation.view']],
            'guard' => 'web',
        ]);
        $user->roles()->attach($role->getKey());

        $this->actingAs($user)
            ->get(FinancialReconciliationDashboard::getUrl())
            ->assertSuccessful()
            ->assertSee('Financial reconciliation diagnostics')
            ->assertSee('No completed statement rows match these filters.');
    }

    /** @return array{0: FinancialAccount, 1: BankStatementImportItem, 2: User} */
    private function completedStatementItem(string $date = '2026-10-15', ?FinancialAccount $account = null, string $currency = 'RON'): array
    {
        $account ??= FinancialAccount::factory()->create(['currency' => 'RON']);
        $actor = User::factory()->create();
        $batch = BankStatementImportBatch::query()->create([
            'financial_account_id' => $account->id,
            'created_by' => $actor->id,
            'bank_code' => 'synthetic',
            'source_sha256' => hash('sha256', uniqid('', true)),
            'parser_version' => 'fixture-v1',
            'status' => BankStatementImportBatch::STATUS_COMPLETED,
            'currency_code' => $currency,
            'statement_start' => $date,
            'statement_end' => $date,
            'transaction_count' => 1,
            'review_required_count' => 0,
            'warning_count' => 0,
            'preview_payload' => [],
            'completed_at' => now(),
        ]);
        $item = BankStatementImportItem::query()->create([
            'batch_id' => $batch->id,
            'financial_account_id' => $account->id,
            'line_fingerprint' => hash('sha256', uniqid('', true)),
            'transaction_date' => $date,
            'direction' => 'money_in',
            'amount_decimal' => '125.00',
            'currency_code' => $currency,
            'transaction_type_code' => 'customer',
            'classification_status' => 'classified',
            'description' => 'Synthetic statement credit',
            'operation_reference' => 'SYNTHETIC-'.uniqid(),
            'document_reference' => null,
            'source_sha256' => $batch->source_sha256,
            'parser_version' => 'fixture-v1',
            'source_page' => 1,
            'source_row' => 1,
            'review_reasons' => [],
        ]);

        return [$account, $item, $actor];
    }

    private function transaction(FinancialAccount $account, string $timestamp, string $direction, string $amount, string $currency): Transaction
    {
        return Transaction::factory()->create([
            'financial_account_id' => $account->id,
            'vendor_invoice_id' => null,
            'customer_invoice_id' => null,
            'tax_id' => null,
            'transaction_type' => 'Customer',
            'transaction_type_code' => $direction,
            'amount_decimal' => $amount,
            'currency_code' => $currency,
            'timestamp' => $timestamp,
        ]);
    }
}
