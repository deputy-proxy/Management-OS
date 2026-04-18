<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('title')
                    ->required(),
                Forms\Components\TextInput::make('status')
                    ->required(),
                Forms\Components\TextInput::make('productable_type')
                    ->label('Productable Type'),
                Forms\Components\TextInput::make('productable_id')
                    ->label('Productable ID'),
            ]);
    }
}
