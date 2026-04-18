<?php

namespace App\Filament\Resources\CampPrices\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class CampPriceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('camp_id'),
                \Filament\Infolists\Components\TextEntry::make('amount'),
                \Filament\Infolists\Components\TextEntry::make('currency'),
                \Filament\Infolists\Components\TextEntry::make('start_on')->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('end_on')->dateTime(),
            ]);
    }
}
