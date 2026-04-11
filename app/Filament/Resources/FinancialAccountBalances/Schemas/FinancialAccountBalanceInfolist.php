<?php

namespace App\Filament\Resources\FinancialAccountBalances\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class FinancialAccountBalanceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('financial_account_id'),
                \Filament\Infolists\Components\TextEntry::make('current_balance'),
                \Filament\Infolists\Components\TextEntry::make('currency'),
                \Filament\Infolists\Components\TextEntry::make('reported_on')->dateTime(),
            ]);
    }
}
