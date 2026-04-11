<?php

namespace App\Filament\Resources\Processes\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
class ProcessForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('title')
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->required(),
                Forms\Components\KeyValue::make('steps')
                    ->required(),
                Forms\Components\Select::make('owner_id')
                    ->relationship('owner', 'full_name')
                    ->required()
            ]);
    }
}
