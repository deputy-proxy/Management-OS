<?php

namespace App\Filament\Resources\TestSubmissions\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\DateTimePicker;

class TestSubmissionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Submission Details')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            Select::make('test_id')
                                ->label('Test')
                                ->relationship('test', 'title')
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
 
                    Grid::make(2)
                        ->schema([
                            Select::make('status')
                                ->options([
                                    'draft'     => 'Draft',
                                    'submitted' => 'Submitted',
                                    'grading'   => 'Grading',
                                    'graded'    => 'Graded',
                                ])
                                ->required()
                                ->default('draft'),
 
                            TextInput::make('score')
                                ->label('Final Score (%)')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100)
                                ->suffix('%')
                                ->nullable()
                                ->helperText('Filled automatically after grading, or can be set manually.'),
                        ]),
 
                    DateTimePicker::make('submission_timestamp')
                        ->label('Submitted At')
                        ->nullable()
                        ->columnSpanFull(),
                ]),
            ]);
    }
}
