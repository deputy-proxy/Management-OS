<?php

namespace App\Filament\Resources\ProductRevenues\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class ProductRevenueForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('product_id')
                    ->relationship('product', 'title')
                    ->required(),
                Forms\Components\Select::make('customer_invoice_id')
                    ->relationship('customerInvoice', 'identifier')
                    ->required(),
                Forms\Components\DatePicker::make('month')
                    ->required(),
                Forms\Components\TextInput::make('amount')->numeric()
                    ->required(),
            ]);
    }
}
