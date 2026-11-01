<?php

namespace App\Services\GoogleAds;

use App\Contracts\Advertising\GoogleAdsCampaignMetricsAdapter;
use App\Models\GoogleAdsImportConfiguration;

class MockGoogleAdsCampaignMetricsAdapter implements GoogleAdsCampaignMetricsAdapter
{
    /** @param list<array<string, mixed>> $rows */
    public function __construct(private readonly array $rows = []) {}

    public function fetch(GoogleAdsImportConfiguration $configuration, string $from, string $to): array
    {
        $selected = $configuration->selected_campaign_ids ?? [];

        return array_values(array_filter($this->rows, function (array $row) use ($configuration, $from, $to, $selected): bool {
            if (($row['account_customer_id'] ?? null) !== $configuration->customer_id
                || ! is_string($row['metric_date'] ?? null)
                || $row['metric_date'] < $from || $row['metric_date'] > $to) {
                return false;
            }
            if ($selected !== [] && ! in_array($row['campaign_id'] ?? null, $selected, true)) {
                return false;
            }
            if ($configuration->currency_code !== null && ($row['currency_code'] ?? null) !== $configuration->currency_code) {
                throw new GoogleAdsAdapterException('currency_mismatch', 'Mock Ads account currency does not match the configured currency.');
            }

            return true;
        }));
    }
}
