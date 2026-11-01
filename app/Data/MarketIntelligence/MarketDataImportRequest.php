<?php

namespace App\Data\MarketIntelligence;

final readonly class MarketDataImportRequest
{
    /** @param array<string, mixed> $filters @param array<int, array<string, mixed>>|null $manualRecords */
    public function __construct(
        public array $filters = [],
        public ?array $manualRecords = null,
    ) {}
}
