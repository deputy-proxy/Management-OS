<?php

namespace App\Filament\Resources\TestTypes\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class TestTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('title')
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->required(),
            ]);
    }
}
