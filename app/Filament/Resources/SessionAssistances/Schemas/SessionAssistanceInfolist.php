<?php

namespace App\Filament\Resources\SessionAssistances\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class SessionAssistanceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('session.identifier'),
                \Filament\Infolists\Components\TextEntry::make('teacher_id'),
                \Filament\Infolists\Components\TextEntry::make('assistance_date')->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('status'),
                \Filament\Infolists\Components\KeyValueEntry::make('review'),
            ]);
    }
}
