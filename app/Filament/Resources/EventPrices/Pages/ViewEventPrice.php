<?php

namespace App\Filament\Resources\EventPrices\Pages;

use App\Filament\Resources\EventPrices\EventPriceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewEventPrice extends ViewRecord
{
    protected static string $resource = EventPriceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

