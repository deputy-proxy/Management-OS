<?php

namespace App\Filament\Resources\DigitalAssets\Pages;

use App\Filament\Resources\DigitalAssets\DigitalAssetResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDigitalAsset extends EditRecord
{
    protected static string $resource = DigitalAssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

