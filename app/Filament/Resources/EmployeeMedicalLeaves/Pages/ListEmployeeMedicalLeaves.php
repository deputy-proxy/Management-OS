<?php

namespace App\Filament\Resources\EmployeeMedicalLeaves\Pages;

use App\Filament\Resources\EmployeeMedicalLeaves\EmployeeMedicalLeaveResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEmployeeMedicalLeaves extends ListRecords
{
    protected static string $resource = EmployeeMedicalLeaveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

