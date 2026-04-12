<?php

namespace App\Filament\Resources\Competitors\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class CompetitorInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('name'),
                \Filament\Infolists\Components\TextEntry::make('site'),
                \Filament\Infolists\Components\TextEntry::make('instagram'),
                \Filament\Infolists\Components\TextEntry::make('facebook'),
                \Filament\Infolists\Components\TextEntry::make('linkedin'),
                \Filament\Infolists\Components\TextEntry::make('tiktok'),
                \Filament\Infolists\Components\KeyValueEntry::make('locations'),
            ]);
    }
}
