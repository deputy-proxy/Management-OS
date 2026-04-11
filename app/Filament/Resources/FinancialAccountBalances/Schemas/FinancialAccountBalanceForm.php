<?php

namespace App\Filament\Resources\FinancialAccountBalances\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class FinancialAccountBalanceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('financial_account_id')
                    ->relationship('financialAccount', 'title')
                    ->required(),
                Forms\Components\TextInput::make('current_balance')->numeric()
                    ->required(),
                Forms\Components\TextInput::make('currency')
                    ->required(),
                Forms\Components\DatePicker::make('reported_on')
                    ->required(),
            ]);
    }
}
