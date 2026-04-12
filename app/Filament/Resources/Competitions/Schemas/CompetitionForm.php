<?php

namespace App\Filament\Resources\Competitions\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class CompetitionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('title')
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->required(),
                Forms\Components\DatePicker::make('start_on')
                    ->required(),
                Forms\Components\DatePicker::make('end_on')
                    ->required(),
                Forms\Components\TextInput::make('maximum_entries')->numeric()
                    ->required(),
                Forms\Components\KeyValue::make('rules')
                    ->required(),
            ]);
    }
}
