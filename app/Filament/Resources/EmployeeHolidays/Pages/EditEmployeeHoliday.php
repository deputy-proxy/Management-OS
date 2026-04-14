<?php

namespace App\Filament\Resources\EmployeeHolidays\Pages;

use App\Filament\Resources\EmployeeHolidays\EmployeeHolidayResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Relaticle\Comments\Filament\Actions\CommentsAction;

class EditEmployeeHoliday extends EditRecord
{
    protected static string $resource = EmployeeHolidayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),            CommentsAction::make(),

        ];
    }
}

