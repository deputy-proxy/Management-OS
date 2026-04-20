<?php

namespace App\Filament\Resources\Processes\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
class ProcessInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('title'),
                TextEntry::make('description'),
                \Filament\Infolists\Components\KeyValueEntry::make('steps'),
                TextEntry::make('owner_id'),
                TextEntry::make('main_topic'),
                \Filament\Infolists\Components\KeyValueEntry::make('visibile_to'),
                RepeatableEntry::make('steps')
                    ->schema([Grid::make(5)->schema([TextEntry::make('data.title')->label('Title'), TextEntry::make('data.actor')->label('Actor'), TextEntry::make('data.content')->label('Description'), TextEntry::make('data.input')->label('Input'), TextEntry::make('data.output')->label('Output')])])
                    ->columnSpanFull()
            ]);
    }
}
