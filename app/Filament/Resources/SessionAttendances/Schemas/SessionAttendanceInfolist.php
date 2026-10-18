<?php

namespace App\Filament\Resources\SessionAttendances\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class SessionAttendanceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('session.identifier'),
                \Filament\Infolists\Components\TextEntry::make('student_id'),
            ]);
    }
}
