<?php

namespace Tests\Feature;

use App\Models\BankStatementImportBatch;
use App\Models\FinancialAccount;
use App\Models\User;
use App\Services\BankStatements\BankStatementImportBatchService;
use App\Services\BankStatements\BankStatementNormalizationResult;
use App\Services\BankStatements\NormalizedBankStatementTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use RuntimeException;
use Tests\TestCase;

class BankStatementImportBatchServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::define('transaction.create', static fn (User $user): bool => true);
        Gate::define('transaction.update', static fn (User $user): bool => true);
    }

    public function test_preview_is_audited_and_same_file_cannot_be_registered_twice_for_account(): void
    {
        [$account, $actor, $result] = $this->validFixture();
        $service = new BankStatementImportBatchService;
        $batch = $service->createPreview('ING', $account, $actor, $result);

        $this->assertSame(BankStatementImportBatch::STATUS_PREVIEWED, $batch->status);
        $this->assertSame(1, $batch->transaction_count);
        $this->assertFalse($batch->validation_summary['requires_review']);
        $this->assertFalse($batch->preview_payload['posting_performed']);
        $this->assertDatabaseHas('bank_statement_import_events', ['batch_id' => $batch->id, 'event_type' => 'preview_created']);

        $this->expectException(RuntimeException::class);
        $service->createPreview('ING', $account, $actor, $result);
    }

    public function test_approved_and_rejected_decisions_are_locked_and_audited_without_posting(): void
    {
        [$account, $actor, $result] = $this->validFixture();
        $service = new BankStatementImportBatchService;
        $approved = $service->createPreview('ING', $account, $actor, $result);
        $approved = $service->approve($approved->id, $actor, 'Reviewed against statement totals');

        $this->assertSame(BankStatementImportBatch::STATUS_APPROVED, $approved->status);
        $this->assertSame($actor->id, $approved->reviewed_by);
        $this->assertDatabaseHas('bank_statement_import_events', ['batch_id' => $approved->id, 'event_type' => 'approved']);
        $this->assertDatabaseCount('transactions', 0);

        $rejected = $service->createPreview('ING', $account, $actor, $this->validFixture($result->sourceSha256.'-second')[2]);
        $rejected = $service->reject($rejected->id, $actor, 'Statement belongs to another period');
        $this->assertSame(BankStatementImportBatch::STATUS_REJECTED, $rejected->status);
        $this->assertDatabaseHas('bank_statement_import_events', ['batch_id' => $rejected->id, 'event_type' => 'rejected']);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_possible_duplicate_transaction_across_different_files_requires_review(): void
    {
        [$account, $actor, $result] = $this->validFixture();
        $service = new BankStatementImportBatchService;
        $service->createPreview('ING', $account, $actor, $result);
        $overlappingResult = new BankStatementNormalizationResult(
            hash('sha256', 'second-file-with-same-line'), $result->parserVersion, $result->statementStart, $result->statementEnd,
            $result->currency, $result->transactions, [],
        );
        $overlap = $service->createPreview('ING', $account, $actor, $overlappingResult);

        $this->assertTrue($overlap->validation_summary['requires_review']);
        $this->assertSame(1, $overlap->validation_summary['potential_duplicate_line_count']);
        $this->expectException(RuntimeException::class);
        $service->approve($overlap->id, $actor, 'Must not bypass duplicate review');
    }

    public function test_preview_rejects_account_currency_mismatch(): void
    {
        [$account, $actor, $result] = $this->validFixture();
        $account->update(['currency' => 'EUR']);
        $this->expectException(\InvalidArgumentException::class);
        (new BankStatementImportBatchService)->createPreview('ING', $account, $actor, $result);
    }

    public function test_batch_with_validation_findings_cannot_be_approved(): void
    {
        [$account, $actor, $result] = $this->validFixture();
        $resultWithWarning = new BankStatementNormalizationResult(
            $result->sourceSha256.'-warning', $result->parserVersion, $result->statementStart, $result->statementEnd,
            $result->currency, $result->transactions, [['code' => 'statement_totals_mismatch', 'page' => 1, 'row' => 0]],
        );
        $service = new BankStatementImportBatchService;
        $batch = $service->createPreview('ING', $account, $actor, $resultWithWarning);

        $this->expectException(RuntimeException::class);
        $service->approve($batch->id, $actor, 'Try to bypass validation');
    }

    public function test_completed_batch_persists_import_items_atomically_without_posting_ledger_transactions(): void
    {
        [$account, $actor, $result] = $this->validFixture();
        $service = new BankStatementImportBatchService;
        $batch = $service->createPreview('ING', $account, $actor, $result);
        $service->approve($batch->id, $actor, 'Verified statement totals and source rows');
        $completed = $service->complete($batch->id, $actor, 'Persisted reviewed statement rows');

        $this->assertSame(BankStatementImportBatch::STATUS_COMPLETED, $completed->status);
        $this->assertSame(1, $completed->items()->count());
        $this->assertArrayNotHasKey('transactions', $completed->preview_payload);
        $this->assertDatabaseHas('bank_statement_import_items', [
            'batch_id' => $batch->id,
            'financial_account_id' => $account->id,
            'amount_decimal' => '10.00',
            'currency_code' => 'RON',
            'source_page' => 1,
            'source_row' => 8,
        ]);
        $this->assertDatabaseHas('bank_statement_import_events', ['batch_id' => $batch->id, 'event_type' => 'import_completed']);
        $this->assertDatabaseCount('transactions', 0);

        $overlappingResult = new BankStatementNormalizationResult(
            hash('sha256', 'statement-overlap-after-completion'), $result->parserVersion, $result->statementStart, $result->statementEnd,
            $result->currency, $result->transactions, [],
        );
        $overlap = $service->createPreview('ING', $account, $actor, $overlappingResult);
        $this->assertTrue($overlap->validation_summary['requires_review']);
        $this->assertSame(1, $overlap->validation_summary['potential_duplicate_line_count']);
    }

    public function test_failed_batch_can_be_retried_with_audited_state_transitions(): void
    {
        [$account, $actor, $result] = $this->validFixture();
        $service = new BankStatementImportBatchService;
        $batch = $service->createPreview('ING', $account, $actor, $result);
        $failed = $service->markFailed($batch->id, $actor, 'temporary_processing_error', 'Temporary failure during validation');
        $this->assertSame(BankStatementImportBatch::STATUS_FAILED, $failed->status);

        $retried = $service->retry($batch->id, $actor, 'Operator requested a controlled retry');
        $this->assertSame(BankStatementImportBatch::STATUS_PREVIEWED, $retried->status);
        $this->assertDatabaseHas('bank_statement_import_events', ['batch_id' => $batch->id, 'event_type' => 'failed']);
        $this->assertDatabaseHas('bank_statement_import_events', ['batch_id' => $batch->id, 'event_type' => 'retry_requested']);
        $this->assertDatabaseCount('transactions', 0);
    }

    /** @return array{FinancialAccount, User, BankStatementNormalizationResult} */
    private function validFixture(?string $hash = null): array
    {
        $account = FinancialAccount::factory()->create(['currency' => 'RON']);
        $actor = User::factory()->create();
        $hash ??= hash('sha256', 'synthetic-statement-'.$account->id);
        $transaction = new NormalizedBankStatementTransaction(
            '2026-09-03', null, 'money_in', '10.00', 'RON', 'fee', 'mapped', 'Synthetic test transaction',
            'REF-001', null, $hash, 'ing-pdf-1.0.0', 1, 8, null, null, [], '10.00', null,
        );
        $result = new BankStatementNormalizationResult($hash, 'ing-pdf-1.0.0', '2026-09-01', '2026-09-30', 'RON', [$transaction], []);

        return [$account, $actor, $result];
    }
}
