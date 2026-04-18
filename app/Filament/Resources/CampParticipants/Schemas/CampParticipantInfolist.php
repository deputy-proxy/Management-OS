<?php

namespace App\Filament\Resources\CampParticipants\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class CampParticipantInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('camp_id'),
                \Filament\Infolists\Components\TextEntry::make('customer_id'),
            ]);
    }
}
