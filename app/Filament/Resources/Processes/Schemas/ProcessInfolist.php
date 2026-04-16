<?php

namespace App\Filament\Resources\Processes\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;
class ProcessInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('title'),
                \Filament\Infolists\Components\TextEntry::make('description'),
                \Filament\Infolists\Components\KeyValueEntry::make('steps'),
                \Filament\Infolists\Components\TextEntry::make('owner_id'),
                \Filament\Infolists\Components\TextEntry::make('main_topic')
            ]);
    }
}
