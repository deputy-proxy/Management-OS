<?php

namespace App\Filament\Resources\Taxes\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class TaxInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('category'),
                \Filament\Infolists\Components\TextEntry::make('month'),
                \Filament\Infolists\Components\TextEntry::make('amount'),
                \Filament\Infolists\Components\TextEntry::make('status'),
                \Filament\Infolists\Components\TextEntry::make('due_on')->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('paid_on')->dateTime(),
            ]);
    }
}
