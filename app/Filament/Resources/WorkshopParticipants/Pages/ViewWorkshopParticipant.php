<?php

namespace App\Filament\Resources\WorkshopParticipants\Pages;

use App\Filament\Resources\WorkshopParticipants\WorkshopParticipantResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewWorkshopParticipant extends ViewRecord
{
    protected static string $resource = WorkshopParticipantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

