<?php

namespace App\Filament\Resources\EmployeePaySlips\Pages;

use App\Filament\Resources\EmployeePaySlips\EmployeePaySlipResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEmployeePaySlip extends EditRecord
{
    protected static string $resource = EmployeePaySlipResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

