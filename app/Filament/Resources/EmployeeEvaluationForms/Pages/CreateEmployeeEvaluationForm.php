<?php

namespace App\Filament\Resources\EmployeeEvaluationForms\Pages;

use App\Filament\Resources\EmployeeEvaluationForms\EmployeeEvaluationFormResource;
use App\Services\EmployeeEvaluationScoringService;
use Filament\Resources\Pages\CreateRecord;

class CreateEmployeeEvaluationForm extends CreateRecord
{
    protected static string $resource = EmployeeEvaluationFormResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        app(EmployeeEvaluationScoringService::class)->validateTemplate($data['criteria'] ?? []);

        return $data;
    }
}
