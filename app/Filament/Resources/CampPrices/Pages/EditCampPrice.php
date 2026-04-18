<?php

namespace App\Filament\Resources\CampPrices\Pages;

use App\Filament\Resources\CampPrices\CampPriceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCampPrice extends EditRecord
{
    protected static string $resource = CampPriceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

