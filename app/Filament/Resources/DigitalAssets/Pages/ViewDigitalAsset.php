<?php

namespace App\Filament\Resources\DigitalAssets\Pages;

use App\Filament\Resources\DigitalAssets\DigitalAssetResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewDigitalAsset extends ViewRecord
{
    protected static string $resource = DigitalAssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

