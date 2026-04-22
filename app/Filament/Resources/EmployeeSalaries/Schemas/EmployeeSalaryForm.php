<?php

namespace App\Filament\Resources\EmployeeSalaries\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class EmployeeSalaryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('employee_id')
                    ->relationship('employee', 'full_name')
                    ->required(),
                Forms\Components\TextInput::make('month')
                    ->required(),
                Builder::make('items')
                    ->blocks([
                        Block::make('item')
                            ->schema([
                                TextInput::make('date')
                                    ->label('Date')
                                    ->required()
                                    ->columnSpan(1),
                                TextInput::make('activity')
                                    ->label('Activity')
                                    ->required()
                                    ->columnSpan(1),
                                TextInput::make('duration')
                                    ->label('Duration')
                                    ->required()
                                    ->columnSpan(1),
                            ])
                            ->columns(2)
                    ]),
                Forms\Components\TextInput::make('amount')->numeric()
                    ->required(),
                Forms\Components\DatePicker::make('due_on')
                    ->required(),
                Forms\Components\DatePicker::make('paid_on')
                    ->required(),
            ]);
    }
}
