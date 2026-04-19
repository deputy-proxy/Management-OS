<?php

namespace App\Filament\Resources\ProductResources\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class ProductResourceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\TextInput::make('amount')->numeric()
                    ->required(),
                Forms\Components\DatePicker::make('month')
                    ->required(),
                Forms\Components\TextInput::make('resourceable_type')
                    ->label('Resourceable Type'),
                Forms\Components\TextInput::make('resourceable_id')
                    ->label('Resourceable ID'),
            ]);
    }
}
