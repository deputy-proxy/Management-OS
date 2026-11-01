<?php

namespace App\Contracts\Advertising;

use App\Models\GoogleAdsImportConfiguration;

interface GoogleAdsCampaignMetricsAdapter
{
    /** @return list<array<string, mixed>> */
    public function fetch(GoogleAdsImportConfiguration $configuration, string $from, string $to): array;
}
