<?php

namespace App\Filament\Resources\PotentialPartners\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class PotentialPartnerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('name'),
                \Filament\Infolists\Components\TextEntry::make('site'),
                \Filament\Infolists\Components\TextEntry::make('contact_person_name'),
                \Filament\Infolists\Components\TextEntry::make('type'),
                \Filament\Infolists\Components\TextEntry::make('benefits_for_us'),
                \Filament\Infolists\Components\TextEntry::make('benefits_for_them'),
                \Filament\Infolists\Components\TextEntry::make('last_contact')->dateTime(),
            ]);
    }
}
