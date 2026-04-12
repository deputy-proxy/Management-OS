<?php

namespace App\Filament\Resources\CustomerInvoices\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class CustomerInvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('customer_id')
                    ->relationship('customer', 'name')
                    ->required(),
                Forms\Components\Select::make('customer_contract_id')
                    ->relationship('customerContract', 'contract_code')
                    ->required(),
                Forms\Components\TextInput::make('amount_without_vat')->numeric()
                    ->required(),
                Forms\Components\TextInput::make('vat')->numeric()
                    ->required(),
                Forms\Components\TextInput::make('month')
                    ->required(),
                Forms\Components\TextInput::make('status')
                    ->required(),
                Forms\Components\TextInput::make('invoice')
                    ->required(),
            ]);
    }
}
