<?php

namespace App\Filament\Resources\SessionAttendances\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class SessionAttendanceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('group_session_id')
                    ->relationship('groupSession', 'id')
                    ->required(),
                Forms\Components\Select::make('student_id')
                    ->relationship('student', 'full_name')
                    ->required(),
            ]);
    }
}
