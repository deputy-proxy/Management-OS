<?php

namespace App\Filament\Resources\WorkshopParticipants\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class WorkshopParticipantInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('workshop_id'),
                \Filament\Infolists\Components\TextEntry::make('customer_id'),
            ]);
    }
}
