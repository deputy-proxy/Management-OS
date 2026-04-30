<?php

namespace App\Filament\Resources\ProductPrices\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class ProductPriceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('price')->numeric()
                    ->required(),
                Forms\Components\TextInput::make('currency')
                    ->required(),
            ]);
    }
}
