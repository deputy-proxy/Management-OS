<?php

namespace App\Filament\Resources\Newsletters\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class NewsletterInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('title'),
                \Filament\Infolists\Components\TextEntry::make('description'),
                \Filament\Infolists\Components\TextEntry::make('audience'),
                \Filament\Infolists\Components\TextEntry::make('content'),
                \Filament\Infolists\Components\TextEntry::make('scheduled_on')->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('delivered_on')->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('status'),
            ]);
    }
}
