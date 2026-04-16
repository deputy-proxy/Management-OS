<?php

namespace App\Filament\Resources\EmployeeMedicalLeaves\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class EmployeeMedicalLeaveForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('employee_id')
                    ->relationship('employee', 'full_name')
                    ->default(
                        fn () => \App\Models\Employee::where('user_id', auth()->id())->value('id')
                    )
                    ->disabled()
                    ->dehydrated()
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
