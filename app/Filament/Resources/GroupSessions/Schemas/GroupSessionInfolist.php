<?php

namespace App\Filament\Resources\GroupSessions\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;
class GroupSessionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('group_id'),
                \Filament\Infolists\Components\TextEntry::make('course_id'),
                \Filament\Infolists\Components\TextEntry::make('teacher_id'),
                \Filament\Infolists\Components\TextEntry::make('start_on')
                    ->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('end_on')
                    ->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('status'),
                \Filament\Infolists\Components\TextEntry::make('identifier')
            ]);
    }
}
