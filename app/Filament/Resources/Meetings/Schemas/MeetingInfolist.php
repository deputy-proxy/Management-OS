<?php

namespace App\Filament\Resources\Meetings\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class MeetingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('subject'),
                \Filament\Infolists\Components\TextEntry::make('description'),
                \Filament\Infolists\Components\TextEntry::make('type'),
                \Filament\Infolists\Components\TextEntry::make('status'),
                \Filament\Infolists\Components\TextEntry::make('starts_at')->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('ends_at')->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('location'),
                \Filament\Infolists\Components\TextEntry::make('organizer'),
                \Filament\Infolists\Components\KeyValueEntry::make('participants'),
            ]);
    }
}
