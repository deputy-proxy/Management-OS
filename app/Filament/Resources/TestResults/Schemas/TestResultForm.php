<?php

namespace App\Filament\Resources\TestResults\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Forms\Components\KeyValue;

class TestResultForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Result Overview')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            Select::make('test_submission_id')
                                ->label('Submission')
                                ->relationship('testSubmission', 'id')
                                ->searchable()
                                ->preload()
                                ->required(),
 
                            Select::make('student_id')
                                ->label('Student')
                                ->relationship('student', 'full_name')
                                ->searchable()
                                ->preload()
                                ->required(),
                        ]),
 
                    Select::make('teacher_id')
                        ->label('Graded By (Teacher)')
                        ->relationship('teacher', 'full_name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->columnSpanFull(),
                ]),
 
            Section::make('Results Breakdown')
                ->description('Overall score summary and per-question feedback.')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextInput::make('results.total_points')
                                ->label('Total Points')
                                ->numeric()
                                ->minValue(0)
                                ->required(),
 
                            TextInput::make('results.earned_points')
                                ->label('Earned Points')
                                ->numeric()
                                ->minValue(0)
                                ->required(),
 
                            TextInput::make('results.percentage')
                                ->label('Percentage (%)')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100)
                                ->suffix('%')
                                ->required(),
                        ]),
 
                    Toggle::make('results.passed')
                        ->label('Passed')
                        ->columnSpanFull(),
 
                    Textarea::make('results.feedback')
                        ->label('Overall Feedback')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
 
            Section::make('Per-Question Feedback')
                ->schema([
                    Repeater::make('results.per_question')
                        ->label('Question Feedback')
                        ->schema([
                            Grid::make(2)
                                ->schema([
                                    
 
                                    TextInput::make('points_awarded')
                                        ->label('Points Awarded')
                                        ->numeric()
                                        ->minValue(0)
                                        ->required(),
                                ]),
 
                            Textarea::make('feedback')
                                ->label('Feedback')
                                ->rows(2)
                                ->columnSpanFull(),
                        ])
                        ->columnSpanFull()
                        ->collapsible()
                        ->collapsed(),
                ])
                ->collapsible(),
        ]);
    }
}
