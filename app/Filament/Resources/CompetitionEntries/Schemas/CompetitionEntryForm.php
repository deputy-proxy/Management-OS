<?php

namespace App\Filament\Resources\CompetitionEntries\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Textarea;

class CompetitionEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('competition_id')
                    ->relationship('competition', 'title')
                    ->required(),
                Forms\Components\TextInput::make('first_name')
                    ->required(),
                Forms\Components\TextInput::make('last_name')
                    ->required(),
                Forms\Components\TextInput::make('full_name')
                    ->required(),
                Forms\Components\TextInput::make('email')
                    ->required(),
                Forms\Components\TextArea::make('content')
                    ->required(),
                Forms\Components\TextInput::make('attachment')
                    ->required(),
            ]);
    }
}
