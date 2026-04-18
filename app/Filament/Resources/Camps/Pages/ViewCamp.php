<?php

namespace App\Filament\Resources\Camps\Pages;

use App\Filament\Resources\Camps\CampResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCamp extends ViewRecord
{
    protected static string $resource = CampResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

