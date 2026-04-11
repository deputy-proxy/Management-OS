<?php

namespace App\Filament\Resources\FinancialAccounts\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;
class FinancialAccountInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('title'),
                \Filament\Infolists\Components\TextEntry::make('type'),
                \Filament\Infolists\Components\TextEntry::make('bank'),
                \Filament\Infolists\Components\TextEntry::make('currency'),
                \Filament\Infolists\Components\TextEntry::make('status'),
                \Filament\Infolists\Components\TextEntry::make('opened_on')
                    ->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('closed_on')
                    ->dateTime()
            ]);
    }
}
