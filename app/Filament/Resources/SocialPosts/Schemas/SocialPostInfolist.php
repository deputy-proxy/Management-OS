<?php

namespace App\Filament\Resources\SocialPosts\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class SocialPostInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('title'),
                \Filament\Infolists\Components\TextEntry::make('description'),
                \Filament\Infolists\Components\TextEntry::make('content_template_id'),
                \Filament\Infolists\Components\TextEntry::make('network'),
                \Filament\Infolists\Components\TextEntry::make('scheduled_on')->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('published_on')->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('status'),
            ]);
    }
}
