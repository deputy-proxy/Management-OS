<?php

namespace App\Filament\Resources\IndividualSessions\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;
class IndividualSessionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('identifier'),
                \Filament\Infolists\Components\TextEntry::make('customer_id'),
                \Filament\Infolists\Components\TextEntry::make('course_id'),
                \Filament\Infolists\Components\TextEntry::make('teacher_id'),
                \Filament\Infolists\Components\TextEntry::make('starts_at')
                    ->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('ends_at')
                    ->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('status')
            ]);
    }
}
