<?php

namespace Tests\Feature;

use App\Models\BankStatementImportBatch;
use App\Models\BankStatementImportItem;
use App\Models\CustomerInvoice;
use App\Models\FinancialAccount;
use App\Models\Tax;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VendorInvoice;
use App\Services\BankStatements\BankStatementMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BankStatementMatchingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(static fn (): bool => true);
    }

    public function test_exact_match_is_audited_without_mutating_the_transaction(): void
    {
        [$item, $actor] = $this->completedItem();
        $transaction = Transaction::factory()->create($this->transactionAttributes([
            'financial_account_id' => $item->financial_account_id,
            'amount_decimal' => '125.00',
            'amount' => 125,
            'currency_code' => 'RON',
        ]));

        $decision = app(BankStatementMatchingService::class)->recordDecision(
            $item->id, $actor, 'exact_match', 'transaction', $transaction->id, 'Same amount and currency on bank statement',
        );

        $this->assertSame('exact_match', $decision->getProperty('outcome'));
        $this->assertSame(false, $decision->getProperty('settlement_performed'));
        $this->assertSame(false, $decision->getProperty('allocation_performed'));
        $this->assertSame('125.00', $transaction->fresh()->amount_decimal);
        $this->assertSame('RON', $transaction->fresh()->currency_code);
        $this->assertSame($item->financial_account_id, $transaction->fresh()->financial_account_id);
    }

    public function test_partial_candidate_is_recorded_without_claiming_settlement(): void
    {
        [$item, $actor] = $this->completedItem();
        $transaction = Transaction::factory()->create($this->transactionAttributes([
            'financial_account_id' => $item->financial_account_id,
            'amount_decimal' => '60.00',
            'amount' => 60,
            'currency_code' => 'RON',
        ]));

        $decision = app(BankStatementMatchingService::class)->recordDecision(
            $item->id, $actor, 'partial_candidate', 'transaction', $transaction->id, 'Possible partial payment; needs review',
        );

        $this->assertSame('partial_candidate', $decision->getProperty('outcome'));
        $this->assertSame(false, $decision->getProperty('settlement_performed'));
        $this->assertSame('60.00', $transaction->fresh()->amount_decimal);
    }

    public function test_exact_transaction_match_rejects_amount_or_currency_mismatch(): void
    {
        [$item, $actor] = $this->completedItem();
        $transaction = Transaction::factory()->create($this->transactionAttributes([
            'financial_account_id' => $item->financial_account_id,
            'amount_decimal' => '999.00',
            'amount' => 999,
            'currency_code' => 'EUR',
        ]));

        try {
            app(BankStatementMatchingService::class)->recordDecision($item->id, $actor, 'exact_match', 'transaction', $transaction->id, 'Test mismatch');
            $this->fail('A mismatched transaction must not be accepted as exact.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('target_id', $exception->errors());
        }
    }

    public function test_target_from_another_financial_account_is_rejected(): void
    {
        [$item, $actor] = $this->completedItem();
        $transaction = Transaction::factory()->create($this->transactionAttributes(['currency_code' => 'RON', 'amount_decimal' => $item->amount_decimal]));

        $this->expectException(ValidationException::class);
        app(BankStatementMatchingService::class)->recordDecision($item->id, $actor, 'partial_candidate', 'transaction', $transaction->id, 'Cross-account target test');
    }

    public function test_revised_decision_keeps_the_previous_decision_in_history(): void
    {
        [$item, $actor] = $this->completedItem();
        $service = app(BankStatementMatchingService::class);
        $first = $service->recordDecision($item->id, $actor, 'unmatched', null, null, 'No clear candidate');
        $second = $service->recordDecision($item->id, $actor, 'ambiguous', null, null, 'Two possible records');

        $this->assertSame('unmatched', $first->fresh()->getProperty('outcome'));
        $this->assertSame('ambiguous', $second->getProperty('outcome'));
        $this->assertSame('unmatched', $second->getProperty('previous_outcome'));
        $this->assertSame(true, $second->getProperty('manual_override'));
        $this->assertSame(2, $item->matchDecisions()->count());
    }

    public function test_same_target_cannot_have_two_active_exact_matches(): void
    {
        [$first, $actor] = $this->completedItem();
        [$second] = $this->completedItem($first->financialAccount);
        $transaction = Transaction::factory()->create($this->transactionAttributes([
            'financial_account_id' => $first->financial_account_id,
            'amount_decimal' => '125.00',
            'amount' => 125,
            'currency_code' => 'RON',
        ]));
        $service = app(BankStatementMatchingService::class);
        $service->recordDecision($first->id, $actor, 'exact_match', 'transaction', $transaction->id, 'First row');

        $this->expectException(ValidationException::class);
        $service->recordDecision($second->id, $actor, 'exact_match', 'transaction', $transaction->id, 'Duplicate row');
    }

    public function test_invalid_outcome_and_empty_reason_are_rejected(): void
    {
        [$item, $actor] = $this->completedItem();
        try {
            app(BankStatementMatchingService::class)->recordDecision($item->id, $actor, 'settled', null, null, 'No');
            $this->fail('Unknown outcomes must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('outcome', $exception->errors());
        }

        $this->expectException(ValidationException::class);
        app(BankStatementMatchingService::class)->recordDecision($item->id, $actor, 'unmatched', null, null, '   ');
    }

    private function transactionAttributes(array $attributes = []): array
    {
        $customerInvoice = CustomerInvoice::factory()->create(['month' => '2026-01-01', 'currency_code' => 'RON']);
        $vendorInvoice = VendorInvoice::factory()->create(['month' => '2026-01-01', 'currency_code' => 'RON']);
        $tax = Tax::factory()->create(['month' => '2026-01-01']);

        return array_merge([
            'customer_invoice_id' => $customerInvoice->id,
            'vendor_invoice_id' => $vendorInvoice->id,
            'tax_id' => $tax->id,
        ], $attributes);
    }

    private function completedItem(?FinancialAccount $account = null): array
    {
        $account ??= FinancialAccount::factory()->create(['currency' => 'RON']);
        $actor = User::factory()->create();
        $batch = BankStatementImportBatch::query()->create([
            'financial_account_id' => $account->id,
            'created_by' => $actor->id,
            'bank_code' => 'test',
            'source_sha256' => hash('sha256', uniqid('', true)),
            'parser_version' => 'test-v1',
            'status' => BankStatementImportBatch::STATUS_COMPLETED,
            'currency_code' => 'RON',
            'statement_start' => '2026-01-01',
            'statement_end' => '2026-01-31',
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
            'transaction_date' => '2026-01-15',
            'direction' => 'debit',
            'amount_decimal' => '125.00',
            'currency_code' => 'RON',
            'classification_status' => 'unclassified',
            'description' => 'Synthetic statement row',
            'source_sha256' => $batch->source_sha256,
            'parser_version' => $batch->parser_version,
            'source_page' => 1,
            'source_row' => random_int(1, 999999),
            'review_reasons' => [],
        ]);

        return [$item, $actor];
    }
}
