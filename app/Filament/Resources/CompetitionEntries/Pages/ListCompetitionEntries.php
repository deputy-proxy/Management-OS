<?php

namespace App\Filament\Resources\CompetitionEntries\Pages;

use App\Filament\Resources\CompetitionEntries\CompetitionEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCompetitionEntries extends ListRecords
{
    protected static string $resource = CompetitionEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

