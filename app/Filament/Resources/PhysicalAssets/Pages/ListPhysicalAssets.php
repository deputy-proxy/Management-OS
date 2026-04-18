<?php

namespace App\Filament\Resources\PhysicalAssets\Pages;

use App\Filament\Resources\PhysicalAssets\PhysicalAssetResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPhysicalAssets extends ListRecords
{
    protected static string $resource = PhysicalAssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

