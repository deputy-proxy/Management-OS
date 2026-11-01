<?php

namespace App\Filament\Resources\MarketIntelligenceMetrics\Pages;

use App\Filament\Resources\MarketIntelligenceMetrics\MarketIntelligenceMetricResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMetrics extends ListRecords
{
    protected static string $resource = MarketIntelligenceMetricResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
