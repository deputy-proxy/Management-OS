<?php

namespace App\Filament\Resources\KpiDefinitions\Pages;

use App\Filament\Resources\KpiDefinitions\KpiDefinitionResource;
use App\Services\KpiDefinitionService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateKpiDefinition extends CreateRecord
{
    protected static string $resource = KpiDefinitionResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(KpiDefinitionService::class)->createDraft($data);
    }
}
