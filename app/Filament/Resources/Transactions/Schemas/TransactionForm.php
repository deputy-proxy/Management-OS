<?php

namespace App\Filament\Resources\Transactions\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
class TransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('financial_account_id')
                    ->relationship('financialAccount', 'title')
                    ->required(),
                Forms\Components\Select::make('vendor_invoice_id')
                    ->relationship('vendorInvoice', 'id'),
                Forms\Components\TextInput::make('transaction_type')
                    ->required(),
                Forms\Components\TextInput::make('amount')
                    ->numeric()
                    ->required(),
                Forms\Components\TextInput::make('payment_method')
                    ->required(),
                Forms\Components\DateTimePicker::make('timestamp')
                    ->required()
            ]);
    }
}
