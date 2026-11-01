<?php

namespace App\Services;

use App\Models\GoogleAdsImportBatch;
use App\Models\GoogleAdsImportConfiguration;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class GoogleAdsCampaignMetricsHealthService
{
    /** @return array{status: string, stale: bool, stale_after_hours: int, last_success_at: CarbonImmutable|null, latest_error_code: string|null, retryable: bool, incomplete_period: bool, duplicate_records: int, invalid_counters: int, currency_count: int} */
    public function inspect(GoogleAdsImportConfiguration $configuration, ?CarbonImmutable $now = null): array
    {
        $now = ($now ?? CarbonImmutable::now($configuration->account_timezone ?: config('app.timezone')))
            ->setTimezone($configuration->account_timezone ?: config('app.timezone'));
        $staleAfter = max(1, (int) config('services.google_ads.stale_after_hours', 36));
        $latestSuccess = $configuration->importBatches()->where('status', GoogleAdsImportBatch::STATUS_COMPLETED)
            ->orderByDesc('completed_at')->first();
        $latestFailure = $configuration->importBatches()->where('status', GoogleAdsImportBatch::STATUS_FAILED)
            ->orderByDesc('started_at')->first();
        $lastSuccessAt = $latestSuccess?->completed_at;
        $stale = $lastSuccessAt === null || $lastSuccessAt->lt($now->subHours($staleAfter));
        [$expectedStart, $expectedEnd] = $configuration->defaultPeriod($now);
        $incomplete = $latestSuccess === null
            || $latestSuccess->period_start->toDateString() > $expectedStart
            || $latestSuccess->period_end->toDateString() < $expectedEnd;

        $metrics = DB::table('google_ads_campaign_daily_metrics')->where('account_customer_id', $configuration->customer_id);
        $duplicates = DB::table('google_ads_campaign_daily_metrics')
            ->select('account_customer_id', 'campaign_id', 'metric_date')
            ->where('account_customer_id', $configuration->customer_id)
            ->groupBy('account_customer_id', 'campaign_id', 'metric_date')
            ->havingRaw('COUNT(*) > 1')->count();
        $invalidCounters = (clone $metrics)->where(function ($query): void {
            $query->where('impressions', '<', 0)->orWhere('clicks', '<', 0)
                ->orWhere('cost_micros', '<', 0)->orWhere('conversions', '<', 0);
        })->count();
        $currencyCount = (clone $metrics)->distinct()->count('currency_code');

        $status = match (true) {
            $duplicates > 0 || $invalidCounters > 0 || $currencyCount > 1 => 'invalid_data',
            $latestFailure !== null && ($latestSuccess === null || $latestFailure->started_at->gt($latestSuccess->started_at)) => $latestFailure->retryable ? 'retryable_error' : 'error',
            $stale => 'stale',
            $incomplete => 'incomplete',
            default => 'healthy',
        };

        return [
            'status' => $status,
            'stale' => $stale,
            'stale_after_hours' => $staleAfter,
            'last_success_at' => $lastSuccessAt,
            'latest_error_code' => $latestFailure?->error_code,
            'retryable' => (bool) $latestFailure?->retryable,
            'incomplete_period' => $incomplete,
            'duplicate_records' => $duplicates,
            'invalid_counters' => $invalidCounters,
            'currency_count' => $currencyCount,
        ];
    }
}
