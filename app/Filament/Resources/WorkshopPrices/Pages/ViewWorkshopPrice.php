<?php

namespace App\Filament\Resources\WorkshopPrices\Pages;

use App\Filament\Resources\WorkshopPrices\WorkshopPriceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewWorkshopPrice extends ViewRecord
{
    protected static string $resource = WorkshopPriceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

