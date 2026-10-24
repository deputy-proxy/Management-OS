<?php

namespace App\Services\BankStatements;

use App\Models\BankStatementImportBatch;
use App\Models\BankStatementImportItem;
use App\Models\FinancialAccount;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\UnauthorizedException;
use InvalidArgumentException;
use RuntimeException;

final class BankStatementImportBatchService
{
    public function createPreview(string $bankCode, FinancialAccount $account, User $actor, BankStatementNormalizationResult $result): BankStatementImportBatch
    {
        $this->assertCanCreate($actor);

        if (! preg_match('/^[a-z0-9_-]{2,32}$/i', $bankCode)) {
            throw new InvalidArgumentException('Bank code is invalid.');
        }

        $this->validateNormalizedResult($result);

        if (strtoupper((string) $account->currency) !== $result->currency) {
            throw new InvalidArgumentException('Statement currency does not match the selected financial account.');
        }

        $payload = $result->toArray();
        $summary = $this->previewDiagnostics($account, $result);
        $duplicateLines = (int) $summary['potential_duplicate_line_count'];

        try {
            return DB::transaction(function () use ($bankCode, $account, $actor, $result, $payload, $summary, $duplicateLines): BankStatementImportBatch {
                $batch = BankStatementImportBatch::query()->create([
                    'financial_account_id' => $account->getKey(),
                    'created_by' => $actor->getKey(),
                    'bank_code' => strtolower($bankCode),
                    'source_sha256' => $result->sourceSha256,
                    'parser_version' => $result->parserVersion,
                    'status' => BankStatementImportBatch::STATUS_PREVIEWED,
                    'currency_code' => $result->currency,
                    'statement_start' => $result->statementStart,
                    'statement_end' => $result->statementEnd,
                    'transaction_count' => count($result->transactions),
                    'review_required_count' => count(array_filter($result->transactions, static fn (NormalizedBankStatementTransaction $transaction): bool => $transaction->reviewReasons !== [])),
                    'warning_count' => count($result->warnings) + $duplicateLines,
                    'preview_payload' => $payload,
                    'validation_summary' => $summary,
                ]);

                $batch->events()->create([
                    'actor_id' => $actor->getKey(),
                    'event_type' => 'preview_created',
                    'from_status' => null,
                    'to_status' => BankStatementImportBatch::STATUS_PREVIEWED,
                    'reason_code' => $summary['requires_review'] ? 'review_required' : 'validation_passed',
                    'metadata' => ['transaction_count' => $batch->transaction_count, 'warning_count' => $batch->warning_count],
                    'created_at' => now(),
                ]);

                return $batch;
            });
        } catch (QueryException $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'bank_statement_batch_account_source_parser_unique') || (str_contains(strtolower($exception->getMessage()), 'unique constraint failed') && str_contains(strtolower($exception->getMessage()), 'bank_statement_import_batches.financial_account_id') && str_contains(strtolower($exception->getMessage()), 'bank_statement_import_batches.parser_version'))) {
                throw new RuntimeException('This statement file has already been previewed for the selected account.', previous: $exception);
            }

            throw $exception;
        }
    }

    private function validateNormalizedResult(BankStatementNormalizationResult $result): void
    {
        if (! preg_match('/^[a-f0-9]{64}$/', $result->sourceSha256)) {
            throw new InvalidArgumentException('Statement source fingerprint must be a SHA-256 hex digest.');
        }
        if (! preg_match('/^[a-z0-9._-]{1,64}$/i', $result->parserVersion)) {
            throw new InvalidArgumentException('Parser version is invalid.');
        }
        if (! preg_match('/^[A-Z]{3}$/', $result->currency)) {
            throw new InvalidArgumentException('Statement currency code is invalid.');
        }
        $start = \DateTimeImmutable::createFromFormat('!Y-m-d', $result->statementStart);
        $end = \DateTimeImmutable::createFromFormat('!Y-m-d', $result->statementEnd);
        if (! $start || ! $end || $start->format('Y-m-d') !== $result->statementStart || $end->format('Y-m-d') !== $result->statementEnd || $start > $end) {
            throw new InvalidArgumentException('Statement period is invalid.');
        }
        if ($result->transactions === []) {
            throw new InvalidArgumentException('A statement with no normalized transaction rows cannot be imported.');
        }

        foreach ($result->transactions as $transaction) {
            if (! preg_match('/^\d{1,17}\.\d{2}$/', $transaction->amount) || ! preg_match('/[1-9]/', $transaction->amount)) {
                throw new InvalidArgumentException('Normalized transaction amount must be a positive decimal with two fractional digits.');
            }
            if (! in_array($transaction->direction, ['money_in', 'money_out'], true)) {
                throw new InvalidArgumentException('Normalized transaction direction is invalid.');
            }
            if ($transaction->currency !== $result->currency || $transaction->sourceSha256 !== $result->sourceSha256 || $transaction->parserVersion !== $result->parserVersion) {
                throw new InvalidArgumentException('Normalized transaction provenance does not match its statement.');
            }
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $transaction->transactionDate);
            if (! $date || $date->format('Y-m-d') !== $transaction->transactionDate || $transaction->sourcePage < 1 || $transaction->sourceRow < 1) {
                throw new InvalidArgumentException('Normalized transaction date or source row provenance is invalid.');
            }
            if (($transaction->transactionType !== null && mb_strlen($transaction->transactionType) > 64) || mb_strlen($transaction->classificationStatus) > 32 || ($transaction->operationReference !== null && mb_strlen($transaction->operationReference) > 128) || ($transaction->documentReference !== null && mb_strlen($transaction->documentReference) > 128)) {
                throw new InvalidArgumentException('Normalized transaction metadata exceeds supported field limits.');
            }
        }
    }

    /** @return array{requires_review: bool, potential_duplicate_line_count: int, duplicate_file: bool, source_seen_with_different_parser_version: bool, findings: list<string>, source_payload_retention: string, posting_performed: bool} */
    public function previewDiagnostics(FinancialAccount $account, BankStatementNormalizationResult $result): array
    {
        $findings = [];
        foreach ($result->warnings as $warning) {
            $findings[] = $warning['code'].' (page '.$warning['page'].', row '.$warning['row'].')';
        }
        foreach ($result->transactions as $transaction) {
            foreach ($transaction->reviewReasons as $reason) {
                $findings[] = $reason.' (page '.$transaction->sourcePage.', row '.$transaction->sourceRow.')';
            }
        }

        $duplicateFile = BankStatementImportBatch::query()
            ->where('financial_account_id', $account->getKey())
            ->where('source_sha256', $result->sourceSha256)
            ->where('parser_version', $result->parserVersion)
            ->exists();
        $sourceSeenWithDifferentParserVersion = BankStatementImportBatch::query()
            ->where('financial_account_id', $account->getKey())
            ->where('source_sha256', $result->sourceSha256)
            ->where('parser_version', '!=', $result->parserVersion)
            ->exists();
        if ($duplicateFile) {
            $findings[] = 'duplicate_statement_file_for_account_and_parser_version';
        }
        if ($sourceSeenWithDifferentParserVersion) {
            $findings[] = 'source_file_seen_with_different_parser_version';
        }

        $lineKeys = [];
        $duplicateLines = 0;
        foreach ($result->transactions as $transaction) {
            $key = $this->lineFingerprint($transaction->toArray());
            if (isset($lineKeys[$key])) {
                $duplicateLines++;
            }
            $lineKeys[$key] = true;
        }

        $previousFingerprints = [];
        $previousBatches = BankStatementImportBatch::query()
            ->where('financial_account_id', $account->getKey())
            ->where('status', '!=', BankStatementImportBatch::STATUS_REJECTED)
            ->get(['preview_payload']);
        foreach ($previousBatches as $previousBatch) {
            foreach ((array) data_get($previousBatch->preview_payload, 'transactions', []) as $previousTransaction) {
                $previousFingerprints[$this->lineFingerprint((array) $previousTransaction)] = true;
            }
        }
        foreach (BankStatementImportItem::query()->where('financial_account_id', $account->getKey())->pluck('line_fingerprint') as $fingerprint) {
            $previousFingerprints[(string) $fingerprint] = true;
        }
        $crossBatchDuplicates = count(array_intersect_key($lineKeys, $previousFingerprints));
        $duplicateLines += $crossBatchDuplicates;
        if ($duplicateLines > 0) {
            $findings[] = 'potential_duplicate_transaction_lines: '.$duplicateLines;
        }

        return [
            'requires_review' => $result->requiresReview() || $duplicateLines > 0 || $duplicateFile || $sourceSeenWithDifferentParserVersion,
            'potential_duplicate_line_count' => $duplicateLines,
            'duplicate_file' => $duplicateFile,
            'source_seen_with_different_parser_version' => $sourceSeenWithDifferentParserVersion,
            'findings' => array_values(array_unique($findings)),
            'source_payload_retention' => 'normalized_preview_only',
            'posting_performed' => false,
        ];
    }

    public function approve(int $batchId, User $actor, string $reason): BankStatementImportBatch
    {
        $this->assertCanReview($actor);

        return $this->transition($batchId, $actor, BankStatementImportBatch::STATUS_APPROVED, 'approved', $reason, true);
    }

    public function reject(int $batchId, User $actor, string $reason): BankStatementImportBatch
    {
        $this->assertCanReview($actor);

        return $this->transition($batchId, $actor, BankStatementImportBatch::STATUS_REJECTED, 'rejected', $reason, false);
    }

    public function complete(int $batchId, User $actor, string $reason): BankStatementImportBatch
    {
        $this->assertCanReview($actor);
        if (trim($reason) === '') {
            throw new InvalidArgumentException('A completion reason is required.');
        }

        return DB::transaction(function () use ($batchId, $actor, $reason): BankStatementImportBatch {
            $batch = BankStatementImportBatch::query()->lockForUpdate()->findOrFail($batchId);
            if ($batch->status !== BankStatementImportBatch::STATUS_APPROVED) {
                throw new RuntimeException('Only approved batches can be ingested.');
            }
            if ((bool) data_get($batch->validation_summary, 'requires_review', true)) {
                throw new RuntimeException('A batch with unresolved validation findings cannot be ingested.');
            }

            $count = 0;
            foreach ((array) data_get($batch->preview_payload, 'transactions', []) as $transaction) {
                $transaction = (array) $transaction;
                $fingerprint = $this->lineFingerprint($transaction);
                BankStatementImportItem::query()->create([
                    'batch_id' => $batch->id,
                    'financial_account_id' => $batch->financial_account_id,
                    'line_fingerprint' => $fingerprint,
                    'transaction_date' => $transaction['transaction_date'],
                    'direction' => $transaction['direction'],
                    'amount_decimal' => $transaction['amount'],
                    'currency_code' => $transaction['currency'],
                    'transaction_type_code' => $transaction['transaction_type'],
                    'classification_status' => $transaction['classification_status'],
                    'description' => $transaction['description'],
                    'operation_reference' => $transaction['operation_reference'],
                    'document_reference' => $transaction['document_reference'],
                    'source_sha256' => $transaction['source_sha256'],
                    'parser_version' => $transaction['parser_version'],
                    'source_page' => $transaction['source_page'],
                    'source_row' => $transaction['source_row'],
                    'review_reasons' => $transaction['review_reasons'],
                ]);
                $count++;
            }

            if ($count !== $batch->transaction_count) {
                throw new RuntimeException('Imported line count does not match the validated preview.');
            }

            $this->recordTransition($batch, $actor, 'import_completed', BankStatementImportBatch::STATUS_COMPLETED, $reason, 'preview_items_persisted');
            $retainedPreview = [
                'source_sha256' => $batch->source_sha256,
                'parser_version' => $batch->parser_version,
                'statement_start' => (string) $batch->statement_start,
                'statement_end' => (string) $batch->statement_end,
                'currency' => $batch->currency_code,
                'transaction_count' => $batch->transaction_count,
                'warning_count' => $batch->warning_count,
                'requires_review' => false,
                'posting_performed' => false,
            ];
            $retainedPreview['transaction_rows_retained_in'] = 'bank_statement_import_items';
            $batch->forceFill([
                'status' => BankStatementImportBatch::STATUS_COMPLETED,
                'completed_at' => now(),
                'decision_reason' => trim($reason),
                'preview_payload' => $retainedPreview,
            ])->save();
            $batch->events()->create([
                'actor_id' => $actor->getKey(),
                'event_type' => 'import_item_count',
                'from_status' => BankStatementImportBatch::STATUS_APPROVED,
                'to_status' => BankStatementImportBatch::STATUS_COMPLETED,
                'reason_code' => 'posting_not_performed',
                'metadata' => ['item_count' => $count, 'posting_performed' => false],
                'created_at' => now(),
            ]);

            return $batch->refresh();
        });
    }

    public function markFailed(int $batchId, User $actor, string $reasonCode, string $reason): BankStatementImportBatch
    {
        $this->assertCanReview($actor);
        if (! preg_match('/^[a-z0-9_]{3,64}$/', $reasonCode) || trim($reason) === '') {
            throw new InvalidArgumentException('A valid failure code and explanation are required.');
        }

        return DB::transaction(function () use ($batchId, $actor, $reasonCode, $reason): BankStatementImportBatch {
            $batch = BankStatementImportBatch::query()->lockForUpdate()->findOrFail($batchId);
            if (! in_array($batch->status, [BankStatementImportBatch::STATUS_PREVIEWED, BankStatementImportBatch::STATUS_APPROVED], true)) {
                throw new RuntimeException('Only active batches can be marked failed.');
            }
            $this->recordTransition($batch, $actor, 'failed', BankStatementImportBatch::STATUS_FAILED, $reason, $reasonCode);
            $batch->forceFill(['status' => BankStatementImportBatch::STATUS_FAILED, 'decision_reason' => trim($reason)])->save();

            return $batch->refresh();
        });
    }

    public function retry(int $batchId, User $actor, string $reason): BankStatementImportBatch
    {
        $this->assertCanReview($actor);
        if (trim($reason) === '') {
            throw new InvalidArgumentException('A retry reason is required.');
        }

        return DB::transaction(function () use ($batchId, $actor, $reason): BankStatementImportBatch {
            $batch = BankStatementImportBatch::query()->lockForUpdate()->findOrFail($batchId);
            if ($batch->status !== BankStatementImportBatch::STATUS_FAILED) {
                throw new RuntimeException('Only failed batches can be retried.');
            }
            $this->recordTransition($batch, $actor, 'retry_requested', BankStatementImportBatch::STATUS_PREVIEWED, $reason, 'retry_requires_revalidation');
            $batch->forceFill(['status' => BankStatementImportBatch::STATUS_PREVIEWED, 'reviewed_by' => null, 'reviewed_at' => null, 'completed_at' => null, 'decision_reason' => $reason])->save();

            return $batch->refresh();
        });
    }

    private function transition(int $batchId, User $actor, string $toStatus, string $event, string $reason, bool $approval): BankStatementImportBatch
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('A review reason is required.');
        }

        return DB::transaction(function () use ($batchId, $actor, $toStatus, $event, $reason, $approval): BankStatementImportBatch {
            $batch = BankStatementImportBatch::query()->lockForUpdate()->findOrFail($batchId);
            if ($batch->status !== BankStatementImportBatch::STATUS_PREVIEWED) {
                throw new RuntimeException('Only previewed batches can be approved or rejected.');
            }
            if ($approval && (bool) data_get($batch->validation_summary, 'requires_review', true)) {
                throw new RuntimeException('A batch with unresolved validation findings cannot be approved.');
            }

            $this->recordTransition($batch, $actor, $event, $toStatus, $reason, null);
            $batch->forceFill([
                'status' => $toStatus,
                'reviewed_by' => $actor->getKey(),
                'reviewed_at' => now(),
                'decision_reason' => trim($reason),
            ])->save();

            return $batch->refresh();
        });
    }

    private function recordTransition(BankStatementImportBatch $batch, User $actor, string $event, string $toStatus, string $reason, ?string $reasonCode): void
    {
        $batch->events()->create([
            'actor_id' => $actor->getKey(),
            'event_type' => $event,
            'from_status' => $batch->status,
            'to_status' => $toStatus,
            'reason_code' => $reasonCode,
            'metadata' => ['reason_length' => mb_strlen(trim($reason)), 'posting_performed' => false],
            'created_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $transaction */
    private function lineFingerprint(array $transaction): string
    {
        $reference = mb_strtoupper(trim((string) ($transaction['operation_reference'] ?? '')));
        $description = mb_strtolower(preg_replace('/\s+/u', ' ', trim((string) ($transaction['description'] ?? ''))) ?? '');

        return hash('sha256', implode('|', [
            (string) ($transaction['transaction_date'] ?? ''),
            (string) ($transaction['direction'] ?? ''),
            (string) ($transaction['amount'] ?? ''),
            (string) ($transaction['currency'] ?? ''),
            $reference !== '' ? 'ref:'.$reference : 'desc:'.$description,
        ]));
    }

    private function assertCanCreate(User $actor): void
    {
        if (! $actor->can('transaction.create')) {
            throw new UnauthorizedException('You are not authorized to create bank statement import previews.');
        }
    }

    private function assertCanReview(User $actor): void
    {
        if (! $actor->can('transaction.update')) {
            throw new UnauthorizedException('You are not authorized to review bank statement import batches.');
        }
    }
}
