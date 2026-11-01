<?php

namespace App\Services\MarketIntelligence;

use App\Data\MarketIntelligence\MarketDataImportRequest;
use App\Data\MarketIntelligence\NormalizedMarketDataRecord;
use App\Exceptions\MarketIntelligence\MarketDataImportException;
use App\Exceptions\MarketIntelligence\MarketDataSourceTimeoutException;
use App\Models\MarketDataImportRun;
use App\Models\MarketTrend;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class MarketDataImportService
{
    public function __construct(private readonly MarketDataSourceRegistry $sources) {}

    public function import(string $sourceKey, MarketDataImportRequest $request, ?User $actor = null): MarketDataImportRun
    {
        if (mb_strlen($sourceKey) > 80) {
            throw new \InvalidArgumentException('Market data source keys cannot exceed 80 characters.');
        }

        $safeFilters = array_filter(
            array_intersect_key($request->filters, array_flip(['period_start', 'period_end', 'market', 'segment', 'metric_key'])),
            static fn (mixed $value): bool => is_scalar($value) && mb_strlen((string) $value) <= 255,
        );
        $run = MarketDataImportRun::query()->create([
            'source_key' => $sourceKey,
            'status' => 'pending',
            'request_metadata' => ['filters' => $safeFilters],
            'started_at' => now(),
            'requested_by' => $actor?->getKey(),
        ]);

        $attempts = max(1, min(5, (int) config('services.market_intelligence.max_attempts', 3)));
        $recordsReceived = 0;

        try {
            $adapter = $this->sources->get($sourceKey);
            $records = [];
            for ($attempt = 1; $attempt <= $attempts; $attempt++) {
                $run->attempt_count = $attempt;
                $run->save();
                try {
                    $attemptRecords = [];
                    foreach ($adapter->fetch($request) as $payload) {
                        if ($payload instanceof NormalizedMarketDataRecord) {
                            $attemptRecords[] = $payload;
                        } elseif (is_array($payload)) {
                            $attemptRecords[] = NormalizedMarketDataRecord::fromArray($payload);
                        } else {
                            throw new MarketDataImportException('Adapter returned an unsupported record type.', 'malformed_payload');
                        }
                    }
                    $records = $attemptRecords;
                    $recordsReceived = count($attemptRecords);
                    break;
                } catch (Throwable $exception) {
                    $recordsReceived = max($recordsReceived, count($attemptRecords));
                    $failure = $this->normalizeException($exception);
                    if (! $failure->retryable || $attempt === $attempts) {
                        throw $failure;
                    }
                    $delay = max(0, min(5000, (int) config('services.market_intelligence.retry_delay_ms', 100)));
                    if ($delay > 0) {
                        usleep($delay * 1000);
                    }
                }
            }

            DB::transaction(function () use ($records, $sourceKey, $run, $recordsReceived): void {
                $imported = 0;
                $skipped = 0;
                foreach ($records as $record) {
                    $idempotencyKey = $sourceKey.':'.$record->idempotencyKey;
                    if (MarketTrend::query()->where('source_idempotency_key', $idempotencyKey)->exists()) {
                        $skipped++;

                        continue;
                    }
                    MarketTrend::query()->create([
                        'name' => $record->name,
                        'category' => $record->category,
                        'metric_key' => $record->metricKey,
                        'value' => $record->value,
                        'unit' => $record->unit,
                        'market' => $record->market,
                        'segment' => $record->segment,
                        'period_start' => $record->periodStart,
                        'period_end' => $record->periodEnd,
                        'source_name' => $sourceKey,
                        'source_url' => $record->sourceUrl,
                        'source_reference' => $record->sourceReference,
                        'source_revision' => $record->sourceRevision,
                        'source_metadata' => array_merge($record->sourceMetadata, ['adapter' => $sourceKey]),
                        'source_idempotency_key' => $idempotencyKey,
                        'source_import_run_id' => $run->getKey(),
                        'source_type' => $sourceKey === 'mock' ? 'synthetic' : ($sourceKey === 'manual' ? 'manual' : 'imported'),
                        'observed_at' => $record->observedAt,
                        'confidence' => $record->confidence,
                        'methodology' => $record->methodology,
                        'metadata' => $record->metadata,
                        'status' => 'active',
                    ]);
                    $imported++;
                }

                $run->forceFill([
                    'status' => 'succeeded', 'records_received' => $recordsReceived,
                    'records_imported' => $imported, 'records_skipped' => $skipped,
                    'finished_at' => now(), 'is_retryable' => false,
                ])->save();
            });

            return $run->refresh();
        } catch (Throwable $exception) {
            $failure = $this->normalizeException($exception);
            $run->forceFill([
                'status' => $failure->errorType === 'source_unavailable' ? 'unavailable' : 'failed',
                'records_received' => $recordsReceived,
                'error_type' => $failure->errorType,
                'error_message' => mb_substr($failure->getMessage(), 0, 2000),
                'is_retryable' => $failure->retryable,
                'finished_at' => now(),
            ])->save();
            Log::warning('Market data import failed.', [
                'import_run_id' => $run->getKey(), 'source_key' => $sourceKey,
                'error_type' => $failure->errorType, 'retryable' => $failure->retryable,
            ]);

            return $run->refresh();
        }
    }

    private function normalizeException(Throwable $exception): MarketDataImportException
    {
        if ($exception instanceof MarketDataImportException) {
            return $exception;
        }
        if ($exception instanceof ConnectionException) {
            return new MarketDataSourceTimeoutException($exception);
        }
        if ($exception instanceof \InvalidArgumentException) {
            return new MarketDataImportException('Market data record failed application validation.', 'malformed_payload', false, $exception);
        }
        if ($exception instanceof QueryException) {
            return new MarketDataImportException('Market data could not be persisted because of a database constraint.', 'persistence_error', false, $exception);
        }

        return new MarketDataImportException('Market data adapter failed. Inspect the import run for details.', 'adapter_error', false, $exception);
    }
}
