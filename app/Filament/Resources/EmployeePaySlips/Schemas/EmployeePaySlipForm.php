<?php

namespace App\Filament\Resources\EmployeePaySlips\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
class EmployeePaySlipForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('employee_id')
                    ->relationship('employee', 'full_name')
                    ->required(),
                Forms\Components\DatePicker::make('month')
                    ->required(),
                Forms\Components\KeyValue::make('items')
                    ->required(),
                Forms\Components\TextInput::make('amount')
                    ->numeric()
                    ->required(),
                Forms\Components\DatePicker::make('due_on')
                    ->required(),
                Forms\Components\DatePicker::make('paid_on')
                    ->required()
            ]);
    }
}
