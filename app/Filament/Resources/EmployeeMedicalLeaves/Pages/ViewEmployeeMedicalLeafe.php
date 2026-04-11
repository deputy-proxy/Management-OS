<?php

namespace App\Filament\Resources\EmployeeMedicalLeaves\Pages;

use App\Filament\Resources\EmployeeMedicalLeaves\EmployeeMedicalLeafeResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewEmployeeMedicalLeafe extends ViewRecord
{
    protected static string $resource = EmployeeMedicalLeafeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

