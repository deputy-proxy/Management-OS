<?php

namespace App\Filament\Resources\LegalDocuments\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class LegalDocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('title')
                    ->required(),
                Forms\Components\TextInput::make('category')
                    ->required(),
                Forms\Components\DatePicker::make('expiration_date')
                    ->required(),
                Forms\Components\TextInput::make('physical_location')
                    ->required(),
                Forms\Components\TextInput::make('document')
                    ->required(),
            ]);
    }
}
