<?php
namespace App\Filament\Resources\CustomerInvoices\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;

class CustomerInvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Invoice Reference')
                    ->description('Unique identifiers and document references for this invoice.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('identifier')
                            ->required(),
                        Forms\Components\TextInput::make('invoice')
                            ->required(),
                    ]),

                Section::make('Customer & Contract')
                    ->description('The customer and contract this invoice is associated with.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('customer_id')
                            ->relationship('customer', 'name')
                            ->required(),
                        Forms\Components\Select::make('customer_contract_id')
                            ->relationship('customerContract', 'contract_code')
                            ->required(),
                    ]),

                Section::make('Financial Details')
                    ->description('Amounts and VAT breakdown for this invoice.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('amount_without_vat')
                            ->label('Amount (excluding VAT)')
                            ->numeric()
                            ->required(),
                        Forms\Components\TextInput::make('vat')
                            ->label('VAT Amount')
                            ->numeric()
                            ->required(),
                    ]),

                Section::make('Billing Period & Status')
                    ->description('The billing month and current processing status of the invoice.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('month')
                            ->required(),
                        Select::make('status')
                            ->options([
                                'Draft' => 'Draft',
                                'Sent' => 'Sent',
                                'Paid' => 'Paid',
                                'Overdue' => 'Overdue',
                            ])
                            ->required(),
                    ]),
            ]);
    }
}