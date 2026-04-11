<?php

namespace App\Filament\Resources\EmployeeHolidays\Pages;

use App\Filament\Resources\EmployeeHolidays\EmployeeHolidayResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEmployeeHolidays extends ListRecords
{
    protected static string $resource = EmployeeHolidayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

