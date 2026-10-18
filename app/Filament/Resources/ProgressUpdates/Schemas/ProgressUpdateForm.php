<?php

namespace App\Filament\Resources\ProgressUpdates\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class ProgressUpdateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('student_id')
                    ->relationship('student', 'full_name')
                    ->required(),
                Forms\Components\Select::make('session_id')
                    ->relationship('session', 'identifier')
                    ->searchable()
                    ->preload()
                    ->helperText('Optional: link this progress update to the group session that provides its academic context.'),
                Forms\Components\Select::make('teacher_id')
                    ->relationship('teacher', 'full_name')
                    ->required(),
                Forms\Components\Textarea::make('update')
                    ->required(),
                Forms\Components\DatePicker::make('update_date')
                    ->required(),
            ]);
    }
}
