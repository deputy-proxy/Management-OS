<?php

namespace App\Filament\Resources\TestSimulations\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class TestSimulationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('title'),
            TextEntry::make('teacher.full_name')->label('Teacher'),
            TextEntry::make('test.title')->label('Test')->placeholder('—'),
            TextEntry::make('group.title')->label('Group')->placeholder('—'),
            TextEntry::make('date')->dateTime(),
            TextEntry::make('number_of_hours')->label('Hours'),
            TextEntry::make('status'),
            TextEntry::make('notes')->placeholder('—'),
        ]);
    }
}
