<?php

namespace App\Filament\Resources\Camps\Pages;

use App\Filament\Resources\Camps\CampResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCamp extends EditRecord
{
    protected static string $resource = CampResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
