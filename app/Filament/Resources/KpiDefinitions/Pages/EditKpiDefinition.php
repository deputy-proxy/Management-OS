<?php

namespace App\Filament\Resources\KpiDefinitions\Pages;

use App\Filament\Resources\KpiDefinitions\KpiDefinitionResource;
use App\Models\KpiDefinition;
use App\Services\KpiDefinitionService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditKpiDefinition extends EditRecord
{
    protected static string $resource = KpiDefinitionResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof KpiDefinition) {
            abort(404);
        }

        return app(KpiDefinitionService::class)->updateDraft($record, $data);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
