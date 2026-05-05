<?php
namespace App\Filament\Resources\EmployeeEvaluationForms\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class EmployeeEvaluationFormForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Evaluation Target')
                    ->description('The job description this evaluation form applies to.')
                    ->schema([
                        Forms\Components\Select::make('job_description_id')
                            ->relationship('jobDescription', 'job_title')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpanFull(),
                    ]),

                Section::make('Evaluation Criteria')
                    ->description('Define the criteria evaluators will use to assess the employee.')
                    ->schema([
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
                                        TextInput::make('weight')
                                            ->label('Weight (%)')
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->suffix('%'),
                                        Textarea::make('description')
                                            ->label('Description / Guidance')
                                            ->placeholder('Describe what is being evaluated...')
                                            ->rows(2)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),

                                Block::make('text_feedback')
                                    ->label('Text Feedback (Open-ended)')
                                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                                    ->schema([
                                        TextInput::make('question')
                                            ->label('Question')
                                            ->placeholder('e.g. What are this employee\'s key strengths?')
                                            ->required()
                                            ->columnSpanFull(),
                                        Textarea::make('description')
                                            ->label('Description / Guidance')
                                            ->placeholder('Optional guidance for the evaluator...')
                                            ->rows(2)
                                            ->columnSpanFull(),
                                    ]),

                                Block::make('yes_no')
                                    ->label('Yes / No Question')
                                    ->icon('heroicon-o-check-circle')
                                    ->schema([
                                        TextInput::make('question')
                                            ->label('Question')
                                            ->placeholder('e.g. Did the employee meet all deadlines?')
                                            ->required()
                                            ->columnSpanFull(),
                                        Textarea::make('description')
                                            ->label('Description / Guidance')
                                            ->placeholder('Optional guidance for the evaluator...')
                                            ->rows(2)
                                            ->columnSpanFull(),
                                    ]),

                            ])
                            ->addActionLabel('Add Criterion')
                            ->reorderable()
                            ->collapsible()
                            ->required(),
                    ]),

            ]);
    }
}