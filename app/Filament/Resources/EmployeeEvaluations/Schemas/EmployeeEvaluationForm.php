<?php

namespace App\Filament\Resources\EmployeeEvaluations\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
class EmployeeEvaluationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('employee_id')
                    ->relationship('employee', 'full_name')
                    ->required(),
                Forms\Components\KeyValue::make('criteria')
                    ->required(),
                Forms\Components\DatePicker::make('due_by')
                    ->required(),
                Forms\Components\DatePicker::make('performed_on')
                    ->required(),
                Forms\Components\Select::make('performed_by')
                    ->relationship('performedBy', 'full_name')
                    ->required()
            ]);
    }
}
