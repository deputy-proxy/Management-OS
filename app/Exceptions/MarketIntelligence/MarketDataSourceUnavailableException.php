<?php

namespace App\Exceptions\MarketIntelligence;

class MarketDataSourceUnavailableException extends MarketDataImportException
{
    public function __construct(string $sourceKey)
    {
        parent::__construct("Market data source [{$sourceKey}] is not configured or enabled.", 'source_unavailable');
    }
}
