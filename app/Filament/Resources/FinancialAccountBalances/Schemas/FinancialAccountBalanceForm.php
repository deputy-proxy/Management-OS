<?php
namespace App\Filament\Resources\FinancialAccountBalances\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class FinancialAccountBalanceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account Reference')
                    ->description('The financial account this balance entry belongs to.')
                    ->columns(1)
                    ->schema([
                        Forms\Components\Select::make('financial_account_id')
                            ->relationship('financialAccount', 'title')
                            ->required(),
                    ]),

                Section::make('Balance Details')
                    ->description('The recorded balance amount, currency and reporting date.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('current_balance')
                            ->numeric()
                            ->required(),
                        Forms\Components\DatePicker::make('reported_on')
                            ->required(),
                    ]),
            ]);
    }
}