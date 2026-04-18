<?php

namespace App\Filament\Resources\CampParticipants\Pages;

use App\Filament\Resources\CampParticipants\CampParticipantResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCampParticipant extends ViewRecord
{
    protected static string $resource = CampParticipantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

