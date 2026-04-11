<?php

namespace App\Filament\Resources\EmployeeEvaluationForms\Pages;

use App\Filament\Resources\EmployeeEvaluationForms\EmployeeEvaluationFormResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEmployeeEvaluationForms extends ListRecords
{
    protected static string $resource = EmployeeEvaluationFormResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

