<?php

namespace App\Filament\Resources\Meetings\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class MeetingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('subject')
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->required(),
                Forms\Components\TextInput::make('type')
                    ->required(),
                Forms\Components\TextInput::make('status')
                    ->required(),
                Forms\Components\DateTimePicker::make('starts_at')
                    ->required(),
                Forms\Components\DateTimePicker::make('ends_at')
                    ->required(),
                Forms\Components\TextInput::make('location')
                    ->required(),
                Forms\Components\Select::make('organizer_id')
                    ->relationship('organizer', 'full_name')
                    ->required(),
                Forms\Components\KeyValue::make('participants')
                    ->required(),
            ]);
    }
}
