<?php

namespace App\Filament\Resources\WorkshopMaterials\Pages;

use App\Filament\Resources\WorkshopMaterials\WorkshopMaterialResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Relaticle\Comments\Filament\Actions\CommentsAction;

class EditWorkshopMaterial extends EditRecord
{
    protected static string $resource = WorkshopMaterialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),            CommentsAction::make(),

        ];
    }
}

