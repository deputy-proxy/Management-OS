<?php

namespace App\Filament\Resources\MeetingTopcis\Pages;

use App\Filament\Resources\MeetingTopcis\MeetingTopciResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewMeetingTopci extends ViewRecord
{
    protected static string $resource = MeetingTopciResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

