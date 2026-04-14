<?php

namespace App\Filament\Resources\EventParticipants\Pages;

use App\Filament\Resources\EventParticipants\EventParticipantResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Relaticle\Comments\Filament\Actions\CommentsAction;

class EditEventParticipant extends EditRecord
{
    protected static string $resource = EventParticipantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),            CommentsAction::make(),

        ];
    }
}

