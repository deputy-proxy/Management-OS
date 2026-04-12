<?php

namespace App\Filament\Resources\SessionAttendances\Pages;

use App\Filament\Resources\SessionAttendances\SessionAttendanceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSessionAttendance extends ViewRecord
{
    protected static string $resource = SessionAttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

