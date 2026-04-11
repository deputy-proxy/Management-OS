<?php

namespace App\Filament\Resources\EmployeeMedicalLeaves\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class EmployeeMedicalLeafeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('employee_id')
                    ->relationship('employee', 'full_name')
                    ->required(),
                Forms\Components\DatePicker::make('start_date')
                    ->required(),
                Forms\Components\DatePicker::make('end_date')
                    ->required(),
                Forms\Components\TextInput::make('document')
                    ->required(),
            ]);
    }
}
