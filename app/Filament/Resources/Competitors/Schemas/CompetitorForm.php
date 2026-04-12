<?php

namespace App\Filament\Resources\Competitors\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class CompetitorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\TextInput::make('site')
                    ->required(),
                Forms\Components\TextInput::make('instagram')
                    ->required(),
                Forms\Components\TextInput::make('facebook')
                    ->required(),
                Forms\Components\TextInput::make('linkedin')
                    ->required(),
                Forms\Components\TextInput::make('tiktok')
                    ->required(),
                Forms\Components\KeyValue::make('locations')
                    ->required(),
            ]);
    }
}
