<?php

namespace App\Filament\Resources\KpiDefinitions\Pages;

use App\Filament\Resources\KpiDefinitions\KpiDefinitionResource;
use Filament\Resources\Pages\EditRecord;

class EditKpiDefinition extends EditRecord
{
    protected static string $resource = KpiDefinitionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
