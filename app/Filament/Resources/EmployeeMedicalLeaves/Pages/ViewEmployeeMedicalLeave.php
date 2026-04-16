<?php

namespace App\Filament\Resources\EmployeeMedicalLeaves\Pages;

use App\Filament\Resources\EmployeeMedicalLeaves\EmployeeMedicalLeaveResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewEmployeeMedicalLeave extends ViewRecord
{
    protected static string $resource = EmployeeMedicalLeaveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

