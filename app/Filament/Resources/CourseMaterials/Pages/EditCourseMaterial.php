<?php

namespace App\Filament\Resources\CourseMaterials\Pages;

use App\Filament\Resources\CourseMaterials\CourseMaterialResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCourseMaterial extends EditRecord
{
    protected static string $resource = CourseMaterialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

