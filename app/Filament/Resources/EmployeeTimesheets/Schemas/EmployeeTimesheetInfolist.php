<?php

namespace App\Filament\Resources\EmployeeTimesheets\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;
class EmployeeTimesheetInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('employee_id'),
                \Filament\Infolists\Components\TextEntry::make('date')
                    ->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('time_start')
                    ->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('time_end')
                    ->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('category'),
                \Filament\Infolists\Components\TextEntry::make('number_of_hours'),
                \Filament\Infolists\Components\TextEntry::make('group_session_id')
            ]);
    }
}
