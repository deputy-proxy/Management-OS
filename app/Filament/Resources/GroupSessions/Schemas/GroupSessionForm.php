<?php

namespace App\Filament\Resources\GroupSessions\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class GroupSessionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('group_id')
                    ->relationship('group', 'title')
                    ->required(),
                Forms\Components\Select::make('course_id')
                    ->relationship('course', 'title')
                    ->required(),
                Forms\Components\Select::make('teacher_id')
                    ->relationship('teacher', 'full_name')
                    ->required(),
                Forms\Components\DateTimePicker::make('start_on')
                    ->required(),
                Forms\Components\DateTimePicker::make('end_on')
                    ->required(),
                Forms\Components\TextInput::make('status')
                    ->required(),
            ]);
    }
}
