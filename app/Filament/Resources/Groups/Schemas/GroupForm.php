<?php

namespace App\Filament\Resources\Groups\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class GroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('title')
                    ->required(),
                Forms\Components\Select::make('course_id')
                    ->relationship('course', 'title')
                    ->required(),
                Forms\Components\Select::make('teacher_id')
                    ->relationship('teacher', 'full_name')
                    ->required(),
            ]);
    }
}
