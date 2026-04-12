<?php

namespace App\Filament\Resources\Partners\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class PartnerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('name'),
                \Filament\Infolists\Components\TextEntry::make('site'),
                \Filament\Infolists\Components\TextEntry::make('contact_person_name'),
                \Filament\Infolists\Components\TextEntry::make('contact_person_email'),
                \Filament\Infolists\Components\TextEntry::make('contact_person_phone'),
                \Filament\Infolists\Components\KeyValueEntry::make('collaborations'),
            ]);
    }
}
