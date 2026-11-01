<?php

namespace App\Services;

use App\Models\GoogleAdsImportBatch;
use App\Models\GoogleAdsImportConfiguration;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class GoogleAdsCampaignMetricsRefreshService
{
    public function __construct(private readonly GoogleAdsCampaignMetricsImporter $importer) {}

    /** @return array{customer_id: string, from: string, to: string, row_count: int, batch_id: int|null, status: string} */
    public function refresh(GoogleAdsImportConfiguration $configuration, ?CarbonImmutable $now = null): array
    {
        if (blank($configuration->account_timezone)) {
            throw ValidationException::withMessages(['account_timezone' => 'Set the Google Ads account timezone before enabling scheduled refresh.']);
        }
        $accountNow = ($now ?? CarbonImmutable::now($configuration->account_timezone))->setTimezone($configuration->account_timezone);
        [$from, $to] = $configuration->defaultPeriod($accountNow);
        $result = $this->importer->import($from, $to, false, $configuration);

        return [
            'customer_id' => $result['customer_id'],
            'from' => $result['from'],
            'to' => $result['to'],
            'row_count' => $result['row_count'],
            'batch_id' => $result['batch_id'],
            'status' => $result['status'],
        ];
    }

    /** @return array{batch_id: int, retry_batch_id: int, status: string} */
    public function retry(GoogleAdsImportBatch $batch, ?CarbonImmutable $now = null): array
    {
        if ($batch->status !== GoogleAdsImportBatch::STATUS_FAILED || ! $batch->retryable) {
            throw ValidationException::withMessages(['batch' => 'Only failed batches explicitly marked retryable can be retried.']);
        }
        if ($batch->next_retry_at !== null && $batch->next_retry_at->gt($now ?? CarbonImmutable::now())) {
            throw ValidationException::withMessages(['batch' => 'Retry is delayed until '.$batch->next_retry_at->toDateTimeString().' by the provider backoff policy.']);
        }
        $configuration = $batch->configuration;
        if (! $configuration instanceof GoogleAdsImportConfiguration || ! $configuration->is_active) {
            throw ValidationException::withMessages(['configuration' => 'The original active Google Ads configuration is unavailable.']);
        }

        $result = $this->importer->import(
            $batch->period_start->toDateString(),
            $batch->period_end->toDateString(),
            false,
            $configuration,
            $batch,
        );

        return [
            'batch_id' => $batch->getKey(),
            'retry_batch_id' => (int) $result['batch_id'],
            'status' => $result['status'],
        ];
    }

    /** @return array{processed: int, completed: int, failed: int, skipped: int} */
    public function refreshActiveConfigurations(?CarbonImmutable $now = null): array
    {
        $summary = ['processed' => 0, 'completed' => 0, 'failed' => 0, 'skipped' => 0];
        GoogleAdsImportConfiguration::query()->where('is_active', true)->orderBy('id')->chunkById(50, function (Collection $configurations) use (&$summary, $now): void {
            foreach ($configurations as $configuration) {
                if (! in_array($configuration->configurationStatus(), ['ready', 'error'], true) || blank($configuration->account_timezone)) {
                    $summary['skipped']++;

                    continue;
                }
                $summary['processed']++;
                try {
                    $this->refresh($configuration, $now);
                    $summary['completed']++;
                } catch (\Throwable) {
                    $summary['failed']++;
                }
            }
        });

        return $summary;
    }
}
