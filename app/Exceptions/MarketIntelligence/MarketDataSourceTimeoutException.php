<?php

namespace App\Exceptions\MarketIntelligence;

class MarketDataSourceTimeoutException extends MarketDataImportException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('Market data source request timed out.', 'timeout', true, $previous);
    }
}
