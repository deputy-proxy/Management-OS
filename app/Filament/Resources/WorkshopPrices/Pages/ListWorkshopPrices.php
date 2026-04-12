<?php

namespace App\Filament\Resources\WorkshopPrices\Pages;

use App\Filament\Resources\WorkshopPrices\WorkshopPriceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWorkshopPrices extends ListRecords
{
    protected static string $resource = WorkshopPriceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

