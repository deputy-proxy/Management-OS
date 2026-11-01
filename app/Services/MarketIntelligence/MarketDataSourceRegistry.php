<?php

namespace App\Services\MarketIntelligence;

use App\Contracts\MarketIntelligence\MarketDataSourceAdapter;
use App\Exceptions\MarketIntelligence\MarketDataSourceUnavailableException;

class MarketDataSourceRegistry
{
    /** @var array<string, MarketDataSourceAdapter> */
    private array $adapters = [];

    public function __construct(ManualMarketDataAdapter $manual, MockMarketDataAdapter $mock)
    {
        $this->register($manual);
        $this->register($mock);
    }

    public function register(MarketDataSourceAdapter $adapter): void
    {
        $key = $adapter->key();
        if (preg_match('/\A[a-z0-9][a-z0-9._-]{0,79}\z/', $key) !== 1) {
            throw new \InvalidArgumentException('Market data adapter keys must be stable lowercase identifiers.');
        }

        $this->adapters[$key] = $adapter;
    }

    public function get(string $key): MarketDataSourceAdapter
    {
        $adapter = $this->adapters[$key] ?? null;
        if ($adapter === null || ! $adapter->isConfigured()) {
            throw new MarketDataSourceUnavailableException($key);
        }

        return $adapter;
    }
}
