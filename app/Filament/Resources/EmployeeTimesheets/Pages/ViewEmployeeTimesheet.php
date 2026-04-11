<?php

namespace App\Filament\Resources\EmployeeTimesheets\Pages;

use App\Filament\Resources\EmployeeTimesheets\EmployeeTimesheetResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewEmployeeTimesheet extends ViewRecord
{
    protected static string $resource = EmployeeTimesheetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

