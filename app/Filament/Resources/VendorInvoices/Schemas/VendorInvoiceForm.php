<?php

namespace App\Filament\Resources\VendorInvoices\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
class VendorInvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('vendor_id')
                    ->relationship('vendor', 'name')
                    ->required(),
                Forms\Components\Select::make('vendor_contract_id')
                    ->relationship('vendorContract', 'contract_code')
                    ->required(),
                Forms\Components\TextInput::make('amount_without_vat')
                    ->numeric()
                    ->required(),
                Forms\Components\TextInput::make('vat')
                    ->numeric()
                    ->required(),
                Forms\Components\TextInput::make('currency')
                    ->required(),
                Forms\Components\TextInput::make('month')
                    ->required(),
                Forms\Components\TextInput::make('status')
                    ->required(),
                Forms\Components\TextInput::make('invoice')
                    ->required()
            ]);
    }
}
