<?php

namespace App\Filament\Resources\WorkshopPrices\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class WorkshopPriceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('workshop_id'),
                \Filament\Infolists\Components\TextEntry::make('amount'),
                \Filament\Infolists\Components\TextEntry::make('currency'),
                \Filament\Infolists\Components\TextEntry::make('start_on')->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('end_on')->dateTime(),
            ]);
    }
}
