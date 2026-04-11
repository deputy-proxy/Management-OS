<?php

namespace App\Filament\Resources\EmployeeMedicalLeaves\Pages;

use App\Filament\Resources\EmployeeMedicalLeaves\EmployeeMedicalLeafeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEmployeeMedicalLeaves extends ListRecords
{
    protected static string $resource = EmployeeMedicalLeafeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

