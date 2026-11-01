<?php

namespace App\Exceptions\MarketIntelligence;

class MalformedMarketDataException extends MarketDataImportException
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, 'malformed_payload', false, $previous);
    }
}
