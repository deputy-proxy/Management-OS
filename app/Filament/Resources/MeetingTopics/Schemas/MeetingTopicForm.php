<?php

namespace App\Filament\Resources\MeetingTopics\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class MeetingTopicForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('title')
                    ->required(),
                Forms\Components\Select::make('meeting_id')
                    ->relationship('meeting', 'subject')
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->required(),
                Forms\Components\TextInput::make('attachment')
                    ->required(),
                Forms\Components\KeyValue::make('outcome')
                    ->required(),
            ]);
    }
}
