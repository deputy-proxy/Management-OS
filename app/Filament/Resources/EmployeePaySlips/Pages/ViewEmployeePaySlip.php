<?php

namespace App\Filament\Resources\EmployeePaySlips\Pages;

use App\Filament\Resources\EmployeePaySlips\EmployeePaySlipResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewEmployeePaySlip extends ViewRecord
{
    protected static string $resource = EmployeePaySlipResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

