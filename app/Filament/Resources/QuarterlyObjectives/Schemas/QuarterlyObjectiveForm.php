<?php

namespace App\Filament\Resources\QuarterlyObjectives\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class QuarterlyObjectiveForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('employee_id')
                    ->relationship('employee', 'full_name')
                    ->required(),
                Forms\Components\TextInput::make('objectives')
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->required(),
                Forms\Components\TextInput::make('quarter')
                    ->required(),
                Forms\Components\TextInput::make('status')
                    ->required(),
            ]);
    }
}
