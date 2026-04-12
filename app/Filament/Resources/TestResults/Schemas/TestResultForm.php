<?php

namespace App\Filament\Resources\TestResults\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class TestResultForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('test_submission_id')
                    ->relationship('testSubmission', 'id')
                    ->required(),
                Forms\Components\Select::make('student_id')
                    ->relationship('student', 'full_name')
                    ->required(),
                Forms\Components\KeyValue::make('results')
                    ->required(),
                Forms\Components\Select::make('teacher_id')
                    ->relationship('teacher', 'full_name')
                    ->required(),
            ]);
    }
}
