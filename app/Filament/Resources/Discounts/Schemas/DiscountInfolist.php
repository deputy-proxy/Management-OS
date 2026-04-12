<?php

namespace App\Filament\Resources\Discounts\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class DiscountInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('applies_to_type'),
                \Filament\Infolists\Components\TextEntry::make('applies_to_item'),
                \Filament\Infolists\Components\TextEntry::make('value'),
                \Filament\Infolists\Components\TextEntry::make('type'),
                \Filament\Infolists\Components\TextEntry::make('start_on')->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('end_on')->dateTime(),
            ]);
    }
}
