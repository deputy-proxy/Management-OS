<?php

namespace App\Filament\Resources\EmployeeEvaluationForms\Pages;

use App\Filament\Resources\EmployeeEvaluationForms\EmployeeEvaluationFormResource;
use App\Services\EmployeeEvaluationScoringService;
use Filament\Resources\Pages\EditRecord;

class EditEmployeeEvaluationForm extends EditRecord
{
    protected static string $resource = EmployeeEvaluationFormResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        app(EmployeeEvaluationScoringService::class)->validateTemplate($data['criteria'] ?? []);

        return $data;
    }
}
