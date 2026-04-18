<?php

namespace App\Filament\Resources\CampPrices\Pages;

use App\Filament\Resources\CampPrices\CampPriceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCampPrice extends ViewRecord
{
    protected static string $resource = CampPriceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

