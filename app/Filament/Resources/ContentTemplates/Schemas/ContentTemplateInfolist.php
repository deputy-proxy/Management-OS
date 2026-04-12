<?php

namespace App\Filament\Resources\ContentTemplates\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class ContentTemplateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('title'),
                \Filament\Infolists\Components\TextEntry::make('description'),
                \Filament\Infolists\Components\TextEntry::make('template_for'),
                \Filament\Infolists\Components\TextEntry::make('link'),
                \Filament\Infolists\Components\TextEntry::make('last_revision')->dateTime(),
            ]);
    }
}
