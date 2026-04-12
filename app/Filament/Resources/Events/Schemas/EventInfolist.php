<?php

namespace App\Filament\Resources\Events\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;
class EventInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('title'),
                \Filament\Infolists\Components\TextEntry::make('description'),
                \Filament\Infolists\Components\TextEntry::make('start_date')
                    ->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('end_date')
                    ->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('start_time')
                    ->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('end_time')
                    ->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('event_price_id'),
                \Filament\Infolists\Components\TextEntry::make('featured_image')
            ]);
    }
}
