<?php

namespace App\Filament\Resources\CompetitionEntries\Pages;

use App\Filament\Resources\CompetitionEntries\CompetitionEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCompetitionEntry extends ViewRecord
{
    protected static string $resource = CompetitionEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

