<?php

namespace App\Filament\Resources\EmployeeMedicalLeaves\Pages;

use App\Filament\Resources\EmployeeMedicalLeaves\EmployeeMedicalLeafeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEmployeeMedicalLeafe extends EditRecord
{
    protected static string $resource = EmployeeMedicalLeafeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

