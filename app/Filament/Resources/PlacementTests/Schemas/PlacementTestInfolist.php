<?php

namespace App\Filament\Resources\PlacementTests\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class PlacementTestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('student_id'),
                \Filament\Infolists\Components\TextEntry::make('scheduled_on')->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('scheduled_at')->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('teacher_id'),
                \Filament\Infolists\Components\TextEntry::make('status'),
                \Filament\Infolists\Components\TextEntry::make('result'),
            ]);
    }
}
