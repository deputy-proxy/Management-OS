<?php

namespace App\Filament\Resources\TestQuestions\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Forms\Components\KeyValue;

class TestQuestionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Question Details')
                ->schema([
                    Select::make('question_type')
                        ->options([
                            'short_answer'      => 'Text (Short Answer)',
                            'long_answer'       => 'Textarea (Long Answer)',
                            'multiple_choice'   => 'Multiple Choice (Single)',
                            'multiple_select'   => 'Multiple Choice (Multiple)',
                            'true_false'        => 'True / False',
                        ])
                        ->required()
                        ->live()
                        ->columnSpanFull(),
 
                    Textarea::make('question')
                        ->label('Question Text')
                        ->required()
                        ->rows(3)
                        ->columnSpanFull(),
 
                    Textarea::make('description')
                        ->label('Additional Description / Hint')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),
 
            // Multiple choice (single correct answer)
            Section::make('Answer Options')
                ->schema([
                    Repeater::make('data')
                        ->label('Options')
                        ->schema([
                            TextInput::make('option')
                                ->required()
                                ->columnSpanFull(),
                        ])
                        ->minItems(2)
                        ->maxItems(8)
                        ->reorderable()
                        ->columnSpanFull(),
 
                    Select::make('correct_answer')
                        ->label('Correct Option')
                        ->options(fn (Get $get) => collect($get('data') ?? [])
                            ->pluck('option', 'option')
                            ->filter()
                            ->toArray())
                        ->required()
                        ->columnSpanFull(),
                ])
                ->visible(fn (Get $get) => $get('question_type') === 'multiple_choice'),
 
            // Multiple select (multiple correct answers)
            Section::make('Answer Options')
                ->schema([
                    Repeater::make('data')
                        ->label('Options')
                        ->schema([
                            TextInput::make('option')
                                ->required()
                                ->columnSpanFull(),
                        ])
                        ->minItems(2)
                        ->maxItems(8)
                        ->reorderable()
                        ->columnSpanFull(),
 
                    Select::make('correct_answer')
                        ->label('Correct Options')
                        ->options(fn (Get $get) => collect($get('data') ?? [])
                            ->pluck('option', 'option')
                            ->filter()
                            ->toArray())
                        ->multiple()
                        ->required()
                        ->columnSpanFull(),
                ])
                ->visible(fn (Get $get) => $get('question_type') === 'multiple_select'),
 
            // True / False
            Section::make('Correct Answer')
                ->schema([
                    Select::make('correct_answer')
                        ->label('Correct Answer')
                        ->options([
                            'true'  => 'True',
                            'false' => 'False',
                        ])
                        ->required()
                        ->columnSpanFull(),
                ])
                ->visible(fn (Get $get) => $get('question_type') === 'true_false'),
 
            // Short / Long answer — model answer for reference
            Section::make('Model Answer')
                ->schema([
                    KeyValue::make('data')
                        ->label('Requirements')
                        ->keyLabel('Minimum words')
                        ->keyLabel('Maximum words')
                        ->editableKeys(false)
                        //->addable(false)
                        ->deletable(false)
                        ->columnSpanFull(),
                    Textarea::make('correct_answer')
                        ->label('Model Answer (for grader reference)')
                        ->rows(4)
                        ->columnSpanFull(),
                ])
                ->visible(fn (Get $get) => in_array($get('question_type'), ['short_answer', 'long_answer'])),
        ]);
    }
}
