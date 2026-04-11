<?php

namespace App\Filament\Resources\EmployeeEvaluations\Pages;

use App\Filament\Resources\EmployeeEvaluations\EmployeeEvaluationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEmployeeEvaluation extends EditRecord
{
    protected static string $resource = EmployeeEvaluationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

