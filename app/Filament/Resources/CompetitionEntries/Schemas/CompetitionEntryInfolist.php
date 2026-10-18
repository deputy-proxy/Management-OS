<?php

namespace App\Filament\Resources\CompetitionEntries\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CompetitionEntryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('competition.title')->label('Competition'),
                TextEntry::make('customer.name')->label('Customer')->placeholder('Public entrant'),
                TextEntry::make('status')->badge(),
                TextEntry::make('first_name'),
                TextEntry::make('last_name'),
                TextEntry::make('full_name'),
                TextEntry::make('email'),
                TextEntry::make('title'),
                TextEntry::make('content'),
                TextEntry::make('attachment'),
            ]);
    }
}
