<?php

namespace App\Filament\Resources\ProductResourceAllocations\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class ProductResourceAllocationForm
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
                Forms\Components\TextInput::make('allocation_percent')->numeric()
                    ->required(),
                Forms\Components\DatePicker::make('month')
                    ->required(),
            ]);
    }
}
