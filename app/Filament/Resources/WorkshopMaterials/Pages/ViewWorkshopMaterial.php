<?php

namespace App\Filament\Resources\WorkshopMaterials\Pages;

use App\Filament\Resources\WorkshopMaterials\WorkshopMaterialResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewWorkshopMaterial extends ViewRecord
{
    protected static string $resource = WorkshopMaterialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

