<?php

namespace App\Console\Commands;

use App\Services\GoogleAdsCampaignMetricsRefreshService;
use Illuminate\Console\Command;

class RefreshGoogleAdsCampaignMetrics extends Command
{
    protected $signature = 'market:refresh-google-ads';

    protected $description = 'Refresh bounded daily Google Ads campaign metrics for active configured accounts.';

    public function handle(GoogleAdsCampaignMetricsRefreshService $refresh): int
    {
        $summary = $refresh->refreshActiveConfigurations();
        $this->info(sprintf(
            'Google Ads refresh: %d completed, %d failed, %d skipped (of %d active configurations).',
            $summary['completed'], $summary['failed'], $summary['skipped'], $summary['processed'] + $summary['skipped'],
        ));

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
