<?php

namespace App\Filament\Resources\TestSubmissions\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class TestSubmissionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('test_id')
                    ->relationship('test', 'title')
                    ->required(),
                Forms\Components\Select::make('student_id')
                    ->relationship('student', 'full_name')
                    ->required(),
                Forms\Components\KeyValue::make('answers')
                    ->required(),
                Forms\Components\DateTimePicker::make('submission_timestamp')
                    ->required(),
            ]);
    }
}
