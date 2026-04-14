<?php

namespace App\Filament\Resources\QuarterlyObjectives\Pages;

use App\Filament\Resources\QuarterlyObjectives\QuarterlyObjectiveResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListQuarterlyObjectives extends ListRecords
{
    protected static string $resource = QuarterlyObjectiveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

