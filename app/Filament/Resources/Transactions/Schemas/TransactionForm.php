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
                    ->relationship('vendorInvoice', 'id')
            ]);
    }
}
