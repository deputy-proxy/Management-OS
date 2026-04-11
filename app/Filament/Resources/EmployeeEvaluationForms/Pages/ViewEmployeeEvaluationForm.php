<?php

namespace App\Filament\Resources\EmployeeEvaluationForms\Pages;

use App\Filament\Resources\EmployeeEvaluationForms\EmployeeEvaluationFormResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewEmployeeEvaluationForm extends ViewRecord
{
    protected static string $resource = EmployeeEvaluationFormResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

