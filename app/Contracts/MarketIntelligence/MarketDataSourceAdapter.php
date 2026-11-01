<?php

namespace App\Contracts\MarketIntelligence;

use App\Data\MarketIntelligence\MarketDataImportRequest;
use App\Data\MarketIntelligence\NormalizedMarketDataRecord;

interface MarketDataSourceAdapter
{
    public function key(): string;

    public function isConfigured(): bool;

    /** @return iterable<NormalizedMarketDataRecord|array<string, mixed>> */
    public function fetch(MarketDataImportRequest $request): iterable;
}
