<?php

namespace App\Filament\Resources\TestSubmissionAnswers\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class TestSubmissionAnswerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('test_submission_id')
                    ->relationship('testSubmission', 'id')
                    ->required(),
                Forms\Components\Select::make('test_question_id')
                    ->relationship('testQuestion', 'question')
                    ->required(),
                Forms\Components\Textarea::make('answer')
                    ->required(),
                Forms\Components\TextInput::make('points')->numeric()
                    ->required(),
                Forms\Components\Select::make('graded_by')
                    ->relationship('gradedBy', 'full_name')
                    ->required(),
            ]);
    }
}
