<?php

namespace App\Filament\Resources\CompetitionEntries\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class CompetitionEntryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('competition_id'),
                \Filament\Infolists\Components\TextEntry::make('first_name'),
                \Filament\Infolists\Components\TextEntry::make('last_name'),
                \Filament\Infolists\Components\TextEntry::make('full_name'),
                \Filament\Infolists\Components\TextEntry::make('email'),
                \Filament\Infolists\Components\KeyValueEntry::make('content'),
            ]);
    }
}
