<?php

namespace App\Filament\Resources\CampParticipants\Pages;

use App\Filament\Resources\CampParticipants\CampParticipantResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCampParticipant extends EditRecord
{
    protected static string $resource = CampParticipantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

