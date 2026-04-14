<?php

namespace App\Filament\Resources\EventParticipants\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class EventParticipantInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('event_id'),
                \Filament\Infolists\Components\TextEntry::make('customer_id'),
            ]);
    }
}
