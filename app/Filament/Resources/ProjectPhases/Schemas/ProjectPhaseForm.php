<?php

namespace App\Filament\Resources\ProjectPhases\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class ProjectPhaseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('title')
                    ->required(),
                Forms\Components\Select::make('project_id')
                    ->relationship('project', 'title')
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->required(),
                Forms\Components\DatePicker::make('start_on')
                    ->required(),
                Forms\Components\DatePicker::make('end_on')
                    ->required(),
                Forms\Components\TextInput::make('order')->numeric()
                    ->required(),
            ]);
    }
}
