<?php

namespace App\Filament\Resources\Articles\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class ArticleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('title'),
                \Filament\Infolists\Components\TextEntry::make('content'),
                \Filament\Infolists\Components\TextEntry::make('published_in'),
                \Filament\Infolists\Components\TextEntry::make('scheduled_on')->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('published_on')->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('status'),
            ]);
    }
}
