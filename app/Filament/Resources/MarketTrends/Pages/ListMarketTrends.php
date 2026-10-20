<?php

namespace App\Filament\Resources\MarketTrends\Pages;

use App\Filament\Resources\MarketTrends\MarketTrendResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMarketTrends extends ListRecords
{
    protected static string $resource = MarketTrendResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
