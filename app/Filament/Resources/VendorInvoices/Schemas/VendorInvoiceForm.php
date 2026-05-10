<?php
namespace App\Filament\Resources\VendorInvoices\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\FileUpload;

class VendorInvoiceForm
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
                        Forms\Components\TextInput::make('invoice_number')
                            ->required(),
                        Forms\Components\TextInput::make('invoice')
                            ->required(),
                    ]),

                Section::make('Vendor & Contract')
                    ->description('The vendor and contract this invoice is associated with.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('vendor_id')
                            ->relationship('vendor', 'name')
                            ->required(),
                        Forms\Components\Select::make('vendor_contract_id')
                            ->relationship('vendorContract', 'contract_code')
                            ->required(),
                    ]),

                Section::make('Financial Details')
                    ->description('Amount, VAT and currency breakdown for this invoice.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('amount')
                            ->numeric()
                            ->required(),
                        Forms\Components\TextInput::make('vat')
                            ->numeric()
                            ->required(),
                        Forms\Components\TextInput::make('currency')
                            ->required(),
                    ]),

                Section::make('Billing Period & Status')
                    ->description('The billing month and current processing status of the invoice.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\DatePicker::make('month')
                            ->required(),
                        Forms\Components\TextInput::make('status')
                            ->required(),
                    ]),
            ]);
    }
}