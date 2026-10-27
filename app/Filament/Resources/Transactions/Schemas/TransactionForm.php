<?php

namespace App\Filament\Resources\Transactions\Schemas;

use App\Models\FinancialAccount;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

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
                        Select::make('financial_account_id')
                            ->relationship('financialAccount', 'title')
                            ->required(),
                        Select::make('vendor_invoice_id')
                            ->relationship('vendorInvoice', 'id'),
                        Select::make('customer_invoice_id')
                            ->relationship('customerInvoice', 'identifier')
                            ->required(),
                        Select::make('tax_id')
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
                        Select::make('transaction_type_code')
                            ->label('Money direction')
                            ->options(['money_in' => 'Money in', 'money_out' => 'Money out'])
                            ->helperText('Required for new records. Legacy rows without an explicit direction remain unclassified.')
                            ->required(),
                        Forms\Components\TextInput::make('amount')
                            ->numeric()
                            ->required(),
                        Forms\Components\TextInput::make('amount_decimal')
                            ->label('Canonical amount')
                            ->numeric()
                            ->minValue(0)
                            ->step('0.01')
                            ->required(fn (?Transaction $record): bool => $record === null)
                            ->helperText('Required for new transactions. Legacy rows without a verified decimal amount remain excluded from canonical cash reporting.'),
                        Select::make('currency_code')
                            ->label('Currency')
                            ->options(function (?Transaction $record): array {
                                $currencies = FinancialAccount::query()->whereNotNull('currency')->where('currency', '!=', '')->distinct()->orderBy('currency')->pluck('currency', 'currency')->all();
                                if (filled($record?->currency_code)) {
                                    $currencies[$record->currency_code] ??= $record->currency_code;
                                }

                                return $currencies;
                            })
                            ->searchable()
                            ->required(fn (?Transaction $record): bool => $record === null)
                            ->helperText('Required for new transactions. Amounts are never converted across currencies.'),
                        Forms\Components\TextInput::make('payment_method')
                            ->required(),
                        Forms\Components\DateTimePicker::make('timestamp')
                            ->required(),
                    ]),

            ]);
    }
}
