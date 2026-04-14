<?php

namespace App\Filament\Resources\WorkshopParticipants\Pages;

use App\Filament\Resources\WorkshopParticipants\WorkshopParticipantResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Relaticle\Comments\Filament\Actions\CommentsAction;

class EditWorkshopParticipant extends EditRecord
{
    protected static string $resource = WorkshopParticipantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),            CommentsAction::make(),

        ];
    }
}

