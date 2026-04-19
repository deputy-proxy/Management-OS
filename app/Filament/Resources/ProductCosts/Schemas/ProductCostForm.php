<?php

namespace App\Filament\Resources\ProductCosts\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class ProductCostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('product_id')
                    ->relationship('product', 'title')
                    ->required(),
                Forms\Components\Select::make('product_resource_id')
                    ->relationship('productResource', 'name')
                    ->required(),
                Forms\Components\DatePicker::make('month')
                    ->required(),
                Forms\Components\TextInput::make('allocation_percent')->numeric()
                    ->required(),
                Forms\Components\TextInput::make('resource_amount')->numeric()
                    ->required(),
                Forms\Components\TextInput::make('allocated_amount')->numeric()
                    ->required(),
            ]);
    }
}
