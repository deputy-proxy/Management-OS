<?php

namespace App\Filament\Resources\MarketTrends\Pages;

use App\Filament\Resources\MarketTrends\MarketTrendResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewMarketTrend extends ViewRecord
{
    protected static string $resource = MarketTrendResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
