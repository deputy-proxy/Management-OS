<?php

namespace App\Services\MarketIntelligence;

use App\Contracts\MarketIntelligence\MarketDataSourceAdapter;
use App\Data\MarketIntelligence\MarketDataImportRequest;
use Carbon\CarbonImmutable;

class MockMarketDataAdapter implements MarketDataSourceAdapter
{
    public function key(): string
    {
        return 'mock';
    }

    public function isConfigured(): bool
    {
        return in_array(config('app.env'), ['local', 'testing'], true);
    }

    public function fetch(MarketDataImportRequest $request): iterable
    {
        $date = (string) ($request->filters['period_start'] ?? '2026-01-01');
        $market = (string) ($request->filters['market'] ?? 'RO');
        yield [
            'idempotency_key' => hash('sha256', implode('|', [$this->key(), $market, $date, 'search_interest_index'])),
            'source_reference' => 'mock:search-interest:'.$market.':'.$date,
            'metric_key' => 'search_interest_index', 'name' => 'Mock search interest',
            'category' => 'search_interest', 'market' => $market, 'segment' => 'adult-online',
            'unit' => 'index', 'value' => '42', 'period_start' => $date,
            'period_end' => CarbonImmutable::parse($date)->endOfMonth()->toDateString(),
            'observed_at' => CarbonImmutable::parse($date)->endOfMonth()->endOfDay()->toIso8601String(),
            'methodology' => 'Deterministic synthetic fixture; not a market observation.',
            'source_metadata' => ['adapter' => $this->key(), 'synthetic' => true],
        ];
    }
}
