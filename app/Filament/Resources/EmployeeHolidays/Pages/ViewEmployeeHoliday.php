<?php

namespace App\Filament\Resources\EmployeeHolidays\Pages;

use App\Filament\Resources\EmployeeHolidays\EmployeeHolidayResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewEmployeeHoliday extends ViewRecord
{
    protected static string $resource = EmployeeHolidayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

