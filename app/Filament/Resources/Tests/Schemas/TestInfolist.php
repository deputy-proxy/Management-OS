<?php

namespace App\Filament\Resources\Tests\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;
class TestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('title'),
                \Filament\Infolists\Components\TextEntry::make('description'),
                \Filament\Infolists\Components\TextEntry::make('test_type_id'),
                \Filament\Infolists\Components\TextEntry::make('topic'),
                \Filament\Infolists\Components\TextEntry::make('level'),
                \Filament\Infolists\Components\KeyValueEntry::make('questions'),
                \Filament\Infolists\Components\TextEntry::make('created_by'),
                \Filament\Infolists\Components\TextEntry::make('time_limit'),
                \Filament\Infolists\Components\TextEntry::make('passing_score')
            ]);
    }
}
