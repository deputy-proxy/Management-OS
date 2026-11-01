<?php

namespace App\Services\GoogleAds;

use RuntimeException;

class GoogleAdsAdapterException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message, public readonly bool $retryable = false)
    {
        parent::__construct($message);
    }
}
