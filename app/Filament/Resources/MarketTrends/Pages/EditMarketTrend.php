<?php

namespace App\Filament\Resources\MarketTrends\Pages;

use App\Filament\Resources\MarketTrends\MarketTrendResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMarketTrend extends EditRecord
{
    protected static string $resource = MarketTrendResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
