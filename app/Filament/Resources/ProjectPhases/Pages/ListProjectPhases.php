<?php

namespace App\Filament\Resources\ProjectPhases\Pages;

use App\Filament\Resources\ProjectPhases\ProjectPhaseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProjectPhases extends ListRecords
{
    protected static string $resource = ProjectPhaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

