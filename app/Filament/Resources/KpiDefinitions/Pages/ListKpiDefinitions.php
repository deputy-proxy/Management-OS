<?php

namespace App\Filament\Resources\KpiDefinitions\Pages;

use App\Filament\Resources\KpiDefinitions\KpiDefinitionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKpiDefinitions extends ListRecords
{
    protected static string $resource = KpiDefinitionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
