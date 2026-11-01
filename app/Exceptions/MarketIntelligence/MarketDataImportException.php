<?php

namespace App\Exceptions\MarketIntelligence;

use RuntimeException;

class MarketDataImportException extends RuntimeException
{
    public function __construct(string $message, public readonly string $errorType, public readonly bool $retryable = false, ?\Throwable $previous = null)
    {
        parent::__construct($message, previous: $previous);
    }
}
