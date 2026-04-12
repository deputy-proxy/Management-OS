<?php

namespace App\Filament\Resources\PlacementTests\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class PlacementTestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('student_id')
                    ->relationship('student', 'last_name')
                    ->required(),
                Forms\Components\DatePicker::make('scheduled_on')
                    ->required(),
                Forms\Components\DateTimePicker::make('scheduled_at')
                    ->required(),
                Forms\Components\Select::make('teacher_id')
                    ->relationship('teacher', 'full_name')
                    ->required(),
                Forms\Components\TextInput::make('status')
                    ->required(),
                Forms\Components\TextInput::make('result')
                    ->required(),
            ]);
    }
}
