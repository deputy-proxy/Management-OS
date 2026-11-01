<?php

namespace App\Filament\Resources\MarketIntelligenceMarkets\Pages;

use App\Filament\Resources\MarketIntelligenceMarkets\MarketIntelligenceMarketResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMarkets extends ListRecords
{
    protected static string $resource = MarketIntelligenceMarketResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
