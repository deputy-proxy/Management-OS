<?php

namespace App\Filament\Resources\CampPrices\Pages;

use App\Filament\Resources\CampPrices\CampPriceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCampPrices extends ListRecords
{
    protected static string $resource = CampPriceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

