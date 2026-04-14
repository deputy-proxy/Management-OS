<?php

namespace App\Filament\Resources\EmployeeEvaluations\Pages;

use App\Filament\Resources\EmployeeEvaluations\EmployeeEvaluationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Relaticle\Comments\Filament\Actions\CommentsAction;

class EditEmployeeEvaluation extends EditRecord
{
    protected static string $resource = EmployeeEvaluationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),            CommentsAction::make(),

        ];
    }
}

