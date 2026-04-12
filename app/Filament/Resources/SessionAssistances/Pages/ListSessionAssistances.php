<?php

namespace App\Filament\Resources\SessionAssistances\Pages;

use App\Filament\Resources\SessionAssistances\SessionAssistanceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSessionAssistances extends ListRecords
{
    protected static string $resource = SessionAssistanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

