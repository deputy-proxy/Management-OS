<?php

namespace App\Filament\Resources\Tests\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class TestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('title')
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->required(),
                Forms\Components\TextInput::make('topic')
                    ->required(),
                Forms\Components\TextInput::make('level')
                    ->required(),
                Forms\Components\KeyValue::make('questions')
                    ->required(),
                Forms\Components\Select::make('created_by')
                    ->relationship('createdBy', 'name')
                    ->required(),
            ]);
    }
}
