<?php

namespace App\Filament\Resources\EmployeeTimesheets\Pages;

use App\Filament\Resources\EmployeeTimesheets\EmployeeTimesheetResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEmployeeTimesheet extends CreateRecord
{
    protected static string $resource = EmployeeTimesheetResource::class;
}

