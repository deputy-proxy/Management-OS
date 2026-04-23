<?php

namespace App\Filament\Resources\EmployeeEvaluationForms\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Radio;

class EmployeeEvaluationFormForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('job_description_id')
                    ->relationship('jobDescription', 'job_title')
                    ->searchable()
                    ->preload()
                    ->required(),

                Builder::make('criteria')
                    ->label('Evaluation Criteria')
                    ->blocks([

                        Block::make('rated_criterion')
                            ->label('Rated Criterion (1–5 scale)')
                            ->icon('heroicon-o-star')
                            ->schema([
                                TextInput::make('label')
                                    ->label('Criterion Label')
                                    ->placeholder('e.g. Communication Skills')
                                    ->required(),
                                Textarea::make('description')
                                    ->label('Description / Guidance')
                                    ->placeholder('Describe what is being evaluated...')
                                    ->rows(2),
                                TextInput::make('weight')
                                    ->label('Weight (%)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->suffix('%'),
                            ])
                            ->columns(2),

                        Block::make('text_feedback')
                            ->label('Text Feedback (Open-ended)')
                            ->icon('heroicon-o-chat-bubble-left-ellipsis')
                            ->schema([
                                TextInput::make('question')
                                    ->label('Question')
                                    ->placeholder('e.g. What are this employee\'s key strengths?')
                                    ->required(),
                                Textarea::make('description')
                                    ->label('Description / Guidance')
                                    ->placeholder('Optional guidance for the evaluator...')
                                    ->rows(2),
                            ]),

                        Block::make('yes_no')
                            ->label('Yes / No Question')
                            ->icon('heroicon-o-check-circle')
                            ->schema([
                                TextInput::make('question')
                                    ->label('Question')
                                    ->placeholder('e.g. Did the employee meet all deadlines?')
                                    ->required(),
                                Textarea::make('description')
                                    ->label('Description / Guidance')
                                    ->placeholder('Optional guidance for the evaluator...')
                                    ->rows(2),
                            ]),

                    ])
                    ->addActionLabel('Add Criterion')
                    ->reorderable()
                    ->collapsible()
                    ->required(),
            ]);
    }
}