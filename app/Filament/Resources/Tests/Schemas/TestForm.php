<?php

namespace App\Filament\Resources\Tests\Schemas;

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

class TestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Information')
                ->schema([
                    TextInput::make('title')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
 
                    Textarea::make('description')
                        ->rows(3)
                        ->columnSpanFull(),
 
                    Grid::make(2)
                        ->schema([
                            TextInput::make('topic')
                                ->maxLength(255),
 
                            Select::make('level')
                                ->options([
                                    'beginner'     => 'Beginner',
                                    'intermediate' => 'Intermediate',
                                    'advanced'     => 'Advanced',
                                ])
                                ->required(),
                        ]),
                ]),
 
            Section::make('Test Configuration')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            Select::make('test_type_id')
                                ->label('Test Type')
                                ->relationship('testType', 'title')
                                ->searchable()
                                ->preload()
                                ->required(),
 
                            Select::make('created_by')
                                ->label('Created By')
                                ->relationship('createdBy', 'name')
                                ->searchable()
                                ->preload()
                                ->required(),
                        ]),
 
                    Grid::make(2)
                        ->schema([
                            TextInput::make('time_limit')
                                ->label('Time Limit (minutes)')
                                ->numeric()
                                ->minValue(1)
                                ->suffix('min'),
 
                            TextInput::make('passing_score')
                                ->label('Passing Score (%)')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100)
                                ->suffix('%'),
                        ]),
                ]),
            ]);
    }
}
