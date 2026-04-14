<?php

namespace App\Filament\Resources\SessionAttendances\Pages;

use App\Filament\Resources\SessionAttendances\SessionAttendanceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Relaticle\Comments\Filament\Actions\CommentsAction;

class EditSessionAttendance extends EditRecord
{
    protected static string $resource = SessionAttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),            CommentsAction::make(),

        ];
    }
}

