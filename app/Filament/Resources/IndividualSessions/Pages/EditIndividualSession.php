<?php

namespace App\Filament\Resources\IndividualSessions\Pages;

use App\Filament\Resources\IndividualSessions\IndividualSessionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditIndividualSession extends EditRecord
{
    protected static string $resource = IndividualSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

