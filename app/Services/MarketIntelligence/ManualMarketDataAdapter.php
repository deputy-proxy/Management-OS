<?php

namespace App\Services\MarketIntelligence;

use App\Contracts\MarketIntelligence\MarketDataSourceAdapter;
use App\Data\MarketIntelligence\MarketDataImportRequest;
use App\Exceptions\MarketIntelligence\MarketDataSourceUnavailableException;

class ManualMarketDataAdapter implements MarketDataSourceAdapter
{
    public function key(): string
    {
        return 'manual';
    }

    public function isConfigured(): bool
    {
        return in_array(config('app.env'), ['local', 'testing'], true);
    }

    public function fetch(MarketDataImportRequest $request): iterable
    {
        if ($request->manualRecords === null) {
            throw new MarketDataSourceUnavailableException($this->key());
        }

        return $request->manualRecords;
    }
}
