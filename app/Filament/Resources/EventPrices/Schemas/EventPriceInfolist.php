<?php

namespace App\Filament\Resources\EventPrices\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class EventPriceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('event_id'),
                \Filament\Infolists\Components\TextEntry::make('amount'),
                \Filament\Infolists\Components\TextEntry::make('currency'),
            ]);
    }
}
