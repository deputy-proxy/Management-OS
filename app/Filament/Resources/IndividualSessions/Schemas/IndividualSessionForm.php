<?php

namespace App\Filament\Resources\IndividualSessions\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
class IndividualSessionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('identifier')
                    ->required(),
                Forms\Components\Select::make('customer_id')
                    ->relationship('customer', 'name')
                    ->required(),
                Forms\Components\Select::make('course_id')
                    ->relationship('course', 'title')
                    ->required(),
                Forms\Components\Select::make('teacher_id')
                    ->relationship('teacher', 'full_name')
                    ->required(),
                Forms\Components\DateTimePicker::make('starts_at')
                    ->required(),
                Forms\Components\DateTimePicker::make('ends_at')
                    ->required(),
                Forms\Components\TextInput::make('status')
                    ->required()
            ]);
    }
}
