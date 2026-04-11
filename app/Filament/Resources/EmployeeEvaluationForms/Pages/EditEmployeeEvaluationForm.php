<?php

namespace App\Filament\Resources\EmployeeEvaluationForms\Pages;

use App\Filament\Resources\EmployeeEvaluationForms\EmployeeEvaluationFormResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEmployeeEvaluationForm extends EditRecord
{
    protected static string $resource = EmployeeEvaluationFormResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

