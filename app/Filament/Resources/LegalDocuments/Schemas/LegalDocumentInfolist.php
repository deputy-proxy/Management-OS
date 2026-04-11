<?php

namespace App\Filament\Resources\LegalDocuments\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class LegalDocumentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('title'),
                \Filament\Infolists\Components\TextEntry::make('category'),
                \Filament\Infolists\Components\TextEntry::make('expiration_date')->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('physical_location'),
                \Filament\Infolists\Components\TextEntry::make('document'),
            ]);
    }
}
