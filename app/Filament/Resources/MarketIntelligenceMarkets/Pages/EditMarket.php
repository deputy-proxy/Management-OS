<?php

namespace App\Filament\Resources\MarketIntelligenceMarkets\Pages;

use App\Filament\Resources\MarketIntelligenceMarkets\MarketIntelligenceMarketResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMarket extends EditRecord
{
    protected static string $resource = MarketIntelligenceMarketResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
