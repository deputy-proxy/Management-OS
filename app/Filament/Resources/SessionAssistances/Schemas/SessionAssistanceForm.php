<?php

namespace App\Filament\Resources\SessionAssistances\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class SessionAssistanceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('group_session_id')
                    ->relationship('groupSession', 'id')
                    ->required(),
                Forms\Components\Select::make('teacher_id')
                    ->relationship('teacher', 'full_name')
                    ->required(),
                Forms\Components\DatePicker::make('assistance_date')
                    ->required(),
                Forms\Components\TextInput::make('status')
                    ->required(),
                Forms\Components\KeyValue::make('review')
                    ->required(),
            ]);
    }
}
