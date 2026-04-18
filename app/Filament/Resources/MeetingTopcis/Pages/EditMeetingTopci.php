<?php

namespace App\Filament\Resources\MeetingTopcis\Pages;

use App\Filament\Resources\MeetingTopcis\MeetingTopciResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMeetingTopci extends EditRecord
{
    protected static string $resource = MeetingTopciResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

