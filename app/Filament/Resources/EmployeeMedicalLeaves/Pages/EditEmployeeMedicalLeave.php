<?php

namespace App\Filament\Resources\EmployeeMedicalLeaves\Pages;

use App\Filament\Resources\EmployeeMedicalLeaves\EmployeeMedicalLeaveResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Relaticle\Comments\Filament\Actions\CommentsAction;

class EditEmployeeMedicalLeave extends EditRecord
{
    protected static string $resource = EmployeeMedicalLeaveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),            
            CommentsAction::make(),
        ];
    }
}

