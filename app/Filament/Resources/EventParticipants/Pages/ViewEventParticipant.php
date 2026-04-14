<?php

namespace App\Filament\Resources\EventParticipants\Pages;

use App\Filament\Resources\EventParticipants\EventParticipantResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewEventParticipant extends ViewRecord
{
    protected static string $resource = EventParticipantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

