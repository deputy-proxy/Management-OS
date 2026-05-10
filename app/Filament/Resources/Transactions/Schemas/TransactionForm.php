<?php
namespace App\Filament\Resources\Transactions\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;

class TransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Transaction References')
                    ->description('The financial account, invoices and tax record linked to this transaction.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('financial_account_id')
                            ->relationship('financialAccount', 'title')
                            ->required(),
                        Forms\Components\Select::make('vendor_invoice_id')
                            ->relationship('vendorInvoice', 'id'),
                        Forms\Components\Select::make('customer_invoice_id')
                            ->relationship('customerInvoice', 'identifier')
                            ->required(),
                        Forms\Components\Select::make('tax_id')
                            ->relationship('tax', 'id')
                            ->required(),
                    ]),

                Section::make('Transaction Details')
                    ->description('The type, amount and payment method for this transaction.')
                    ->columns(2)
                    ->schema([
                        Select::make('transaction_type')
                            ->options([
                                'Customer' => 'Customer',
                                'Vendor' => 'Vendor',
                                'Tax' => 'Tax',
                                'Other' => 'Other',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('amount')
                            ->numeric()
                            ->required(),
                        Forms\Components\TextInput::make('payment_method')
                            ->required(),
                        Forms\Components\DateTimePicker::make('timestamp')
                            ->required(),
                    ]),

            ]);
    }
}