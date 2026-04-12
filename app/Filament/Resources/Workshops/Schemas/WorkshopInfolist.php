<?php

namespace App\Filament\Resources\Workshops\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class WorkshopInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('title'),
                \Filament\Infolists\Components\TextEntry::make('description'),
                \Filament\Infolists\Components\TextEntry::make('start_date')->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('end_date')->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('start_time')->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('end_time')->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('type'),
                \Filament\Infolists\Components\TextEntry::make('location'),
                \Filament\Infolists\Components\TextEntry::make('workshop_price_id'),
            ]);
    }
}
