<?php

namespace App\Filament\Resources\ProgressUpdates\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class ProgressUpdateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('student_id'),
                \Filament\Infolists\Components\TextEntry::make('teacher_id'),
                \Filament\Infolists\Components\TextEntry::make('update'),
                \Filament\Infolists\Components\TextEntry::make('update_date')->dateTime(),
            ]);
    }
}
