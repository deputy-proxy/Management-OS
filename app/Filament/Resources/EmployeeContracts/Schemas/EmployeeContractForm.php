<?php

namespace App\Filament\Resources\EmployeeContracts\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class EmployeeContractForm
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
                Forms\Components\TextInput::make('contract_type')
                    ->required(),
                Forms\Components\TextInput::make('base_salary')->numeric()
                    ->required(),
                Forms\Components\TextInput::make('hourly_rate')->numeric()
                    ->required(),
            ]);
    }
}
