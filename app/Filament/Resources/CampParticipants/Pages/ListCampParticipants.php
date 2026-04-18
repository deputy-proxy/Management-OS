<?php

namespace App\Filament\Resources\CampParticipants\Pages;

use App\Filament\Resources\CampParticipants\CampParticipantResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCampParticipants extends ListRecords
{
    protected static string $resource = CampParticipantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

