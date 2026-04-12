<?php

namespace App\Filament\Resources\SessionAssistances\Pages;

use App\Filament\Resources\SessionAssistances\SessionAssistanceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSessionAssistance extends EditRecord
{
    protected static string $resource = SessionAssistanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

