<?php

namespace App\Filament\Resources\EmployeeTimesheets\Pages;

use App\Filament\Resources\EmployeeTimesheets\EmployeeTimesheetResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEmployeeTimesheet extends EditRecord
{
    protected static string $resource = EmployeeTimesheetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

