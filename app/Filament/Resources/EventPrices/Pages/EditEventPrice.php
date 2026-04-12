<?php

namespace App\Filament\Resources\EventPrices\Pages;

use App\Filament\Resources\EventPrices\EventPriceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEventPrice extends EditRecord
{
    protected static string $resource = EventPriceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

