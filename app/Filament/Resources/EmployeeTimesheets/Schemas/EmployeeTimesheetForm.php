<?php

namespace App\Filament\Resources\EmployeeTimesheets\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class EmployeeTimesheetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('employee_id')
                    ->relationship('employee', 'full_name')
                    ->required(),
                Forms\Components\DatePicker::make('date')
                    ->required(),
                Forms\Components\DateTimePicker::make('time_start')
                    ->required(),
                Forms\Components\DateTimePicker::make('time_end')
                    ->required(),
                Forms\Components\TextInput::make('category')
                    ->required(),
                Forms\Components\TextInput::make('number_of_hours')->numeric()
                    ->required(),
                Forms\Components\Select::make('session_id')
                    ->relationship('session', 'id')
                    ->required(),
            ]);
    }
}
