<?php

namespace Tests\Feature;

use App\Models\BankStatementImportBatch;
use App\Models\BankStatementImportItem;
use App\Models\FinancialAccount;
use App\Models\User;
use App\Services\BankStatements\BankStatementImportBatchService;
use App\Services\BankStatements\BankStatementNormalizationResult;
use App\Services\BankStatements\NormalizedBankStatementTransaction;
use Illuminate\Database\QueryException;
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

    public function test_preview_diagnostics_are_read_only_until_the_operator_confirms_persistence(): void
    {
        [$account, $actor, $result] = $this->validFixture();
        $service = new BankStatementImportBatchService;

        $diagnostics = $service->previewDiagnostics($account, $result);

        $this->assertFalse($diagnostics['requires_review']);
        $this->assertSame([], $diagnostics['findings']);
        $this->assertFalse($diagnostics['posting_performed']);
        $this->assertDatabaseCount('bank_statement_import_batches', 0);
        $this->assertDatabaseCount('bank_statement_import_items', 0);
        $this->assertDatabaseCount('transactions', 0);
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

    public function test_same_source_file_with_a_new_parser_version_is_traceable_but_flagged_for_review(): void
    {
        [$account, $actor, $result] = $this->validFixture();
        $service = new BankStatementImportBatchService;
        $original = $service->createPreview('ING', $account, $actor, $result);
        $sourceTransaction = $result->transactions[0];
        $reparsedTransaction = new NormalizedBankStatementTransaction(
            $sourceTransaction->transactionDate,
            $sourceTransaction->valueDate,
            $sourceTransaction->direction,
            $sourceTransaction->amount,
            $sourceTransaction->currency,
            $sourceTransaction->transactionType,
            $sourceTransaction->classificationStatus,
            $sourceTransaction->description,
            $sourceTransaction->operationReference,
            $sourceTransaction->documentReference,
            $sourceTransaction->sourceSha256,
            'ing-pdf-1.1.0',
            $sourceTransaction->sourcePage,
            $sourceTransaction->sourceRow,
            $sourceTransaction->relatedSourcePage,
            $sourceTransaction->relatedSourceRow,
            $sourceTransaction->reviewReasons,
            $sourceTransaction->balanceAfter,
            $sourceTransaction->counterparty,
        );
        $reparsed = new BankStatementNormalizationResult(
            $result->sourceSha256, 'ing-pdf-1.1.0', $result->statementStart, $result->statementEnd,
            $result->currency, [$reparsedTransaction], [],
        );
        $diagnostics = $service->previewDiagnostics($account, $reparsed);
        $newBatch = $service->createPreview('ING', $account, $actor, $reparsed);

        $this->assertSame('ing-pdf-1.0.0', $original->parser_version);
        $this->assertSame('ing-pdf-1.1.0', $newBatch->parser_version);
        $this->assertTrue($diagnostics['source_seen_with_different_parser_version']);
        $this->assertTrue($newBatch->validation_summary['requires_review']);
        $this->assertContains('source_file_seen_with_different_parser_version', $diagnostics['findings']);
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

        $rejected = $service->createPreview('ING', $account, $actor, $this->validFixture(hash('sha256', $result->sourceSha256.'-second'))[2]);
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
        $newHash = hash('sha256', 'second-file-with-same-line');
        $overlappingResult = new BankStatementNormalizationResult(
            $newHash, $result->parserVersion, $result->statementStart, $result->statementEnd,
            $result->currency, [$this->transactionWithProvenance($result->transactions[0], $newHash)], [],
        );
        $overlap = $service->createPreview('ING', $account, $actor, $overlappingResult);

        $this->assertTrue($overlap->validation_summary['requires_review']);
        $this->assertSame(1, $overlap->validation_summary['potential_duplicate_line_count']);
        $this->expectException(RuntimeException::class);
        $service->approve($overlap->id, $actor, 'Must not bypass duplicate review');
    }

    public function test_invalid_normalized_provenance_is_rejected_before_batch_persistence(): void
    {
        [$account, $actor, $result] = $this->validFixture();
        $wrongHash = hash('sha256', 'different-source');
        $invalid = new BankStatementNormalizationResult(
            $result->sourceSha256, $result->parserVersion, $result->statementStart, $result->statementEnd,
            $result->currency, [$this->transactionWithProvenance($result->transactions[0], $wrongHash)], [],
        );

        $this->expectException(\InvalidArgumentException::class);
        (new BankStatementImportBatchService)->createPreview('ING', $account, $actor, $invalid);
    }

    public function test_invalid_locale_amount_cannot_bypass_normalization_validation(): void
    {
        [$account, $actor, $result] = $this->validFixture();
        $invalid = new BankStatementNormalizationResult(
            $result->sourceSha256, $result->parserVersion, $result->statementStart, $result->statementEnd,
            $result->currency, [$this->transactionWithProvenance($result->transactions[0], $result->sourceSha256, null, '1,000.00')], [],
        );

        $this->expectException(\InvalidArgumentException::class);
        (new BankStatementImportBatchService)->createPreview('ING', $account, $actor, $invalid);
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
            $result->sourceSha256, $result->parserVersion, $result->statementStart, $result->statementEnd,
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

        $newHash = hash('sha256', 'statement-overlap-after-completion');
        $overlappingResult = new BankStatementNormalizationResult(
            $newHash, $result->parserVersion, $result->statementStart, $result->statementEnd,
            $result->currency, [$this->transactionWithProvenance($result->transactions[0], $newHash)], [],
        );
        $overlap = $service->createPreview('ING', $account, $actor, $overlappingResult);
        $this->assertTrue($overlap->validation_summary['requires_review']);
        $this->assertSame(1, $overlap->validation_summary['potential_duplicate_line_count']);
        $this->assertDatabaseCount('bank_statement_import_items', 1);

        $item = $completed->items()->firstOrFail();
        $this->expectException(QueryException::class);
        BankStatementImportItem::query()->create([
            'batch_id' => $overlap->id,
            'financial_account_id' => $item->financial_account_id,
            'line_fingerprint' => $item->line_fingerprint,
            'transaction_date' => $item->transaction_date,
            'direction' => $item->direction,
            'amount_decimal' => $item->amount_decimal,
            'currency_code' => $item->currency_code,
            'transaction_type_code' => $item->transaction_type_code,
            'classification_status' => $item->classification_status,
            'description' => $item->description,
            'operation_reference' => $item->operation_reference,
            'document_reference' => $item->document_reference,
            'source_sha256' => $item->source_sha256,
            'parser_version' => $item->parser_version,
            'source_page' => $item->source_page,
            'source_row' => $item->source_row,
            'review_reasons' => $item->review_reasons,
        ]);
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

    private function transactionWithProvenance(NormalizedBankStatementTransaction $source, string $sourceSha256, ?string $parserVersion = null, ?string $amount = null): NormalizedBankStatementTransaction
    {
        return new NormalizedBankStatementTransaction(
            $source->transactionDate,
            $source->valueDate,
            $source->direction,
            $amount ?? $source->amount,
            $source->currency,
            $source->transactionType,
            $source->classificationStatus,
            $source->description,
            $source->operationReference,
            $source->documentReference,
            $sourceSha256,
            $parserVersion ?? $source->parserVersion,
            $source->sourcePage,
            $source->sourceRow,
            $source->relatedSourcePage,
            $source->relatedSourceRow,
            $source->reviewReasons,
            $source->balanceAfter,
            $source->counterparty,
        );
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
