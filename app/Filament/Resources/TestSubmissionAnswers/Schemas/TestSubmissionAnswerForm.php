<?php

namespace App\Filament\Resources\TestSubmissionAnswers\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Forms\Components\KeyValue;

class TestSubmissionAnswerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Answer')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            Select::make('test_submission_id')
                                ->label('Submission')
                                ->relationship('testSubmission', 'id')
                                ->searchable()
                                ->preload()
                                ->required(),
 
                            Select::make('test_question_id')
                                ->label('Question')
                                ->relationship('testQuestion', 'question')
                                ->searchable()
                                ->preload()
                                ->required(),
                        ]),
 
                    Textarea::make('answer')
                        ->label('Student Answer')
                        ->rows(4)
                        ->columnSpanFull(),
                ]),
 
            Section::make('Grading')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextInput::make('points')
                                ->label('Points Awarded')
                                ->numeric()
                                ->minValue(0)
                                ->nullable(),
 
                            Select::make('graded_by')
                                ->label('Graded By')
                                ->relationship('gradedBy', 'full_name')
                                ->searchable()
                                ->preload()
                                ->nullable(),
                        ]),
                ]),
 
            Section::make('Interaction Metadata')
                ->description('Automatically captured during the test session. Read-only.')
                ->schema([
                    KeyValue::make('metadata')
                        ->label('Metadata')
                        ->keyLabel('Field')
                        ->valueLabel('Value')
                        ->columnSpanFull()
                        ->disabled(), // metadata is written by the frontend, not manually
                ])
                ->collapsed()
                ->collapsible(),
        ]);
    }
}
