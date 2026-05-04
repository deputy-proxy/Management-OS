<?php
namespace App\Filament\Resources\Processes\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Hexters\HexaLite\Models\HexaRole;

class ProcessForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('General Information')
                    ->description('Basic details about this process.')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('description')
                            ->required()
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('main_topic')
                            ->required(),
                    ]),

                Section::make('Ownership & Visibility')
                    ->description('Define who owns this process and who can see it.')
                    ->schema([
                        Forms\Components\Select::make('owner_id')
                            ->relationship('owner', 'full_name')
                            ->required(),
                        Forms\Components\CheckboxList::make('visible_to')
                            ->label('Visible To')
                            ->options(
                                HexaRole::query()
                                    ->pluck('name', 'id')
                                    ->toArray()
                            )
                            ->columns(2)
                            ->required(),
                    ]),

                Section::make('Steps')
                    ->description('Define the steps and conditions that make up this process.')
                    ->schema([
                        Builder::make('steps')
                            ->blocks([
                                Block::make('step')
                                    ->schema([
                                        TextInput::make('title')
                                            ->label('Title')
                                            ->required(),
                                        Select::make('actor')
                                            ->options([
                                                HexaRole::query()
                                                    ->pluck('name', 'id')
                                                    ->toArray()
                                            ])
                                            ->required(),
                                        Textarea::make('content')
                                            ->label('Description')
                                            ->required()
                                            ->columnSpanFull(),
                                        TextInput::make('input')
                                            ->label('Input')
                                            ->required(),
                                        TextInput::make('output')
                                            ->label('Output')
                                            ->required(),
                                    ])
                                    ->columns(2),
                                Block::make('condition')
                                    ->schema([
                                        Textarea::make('content')
                                            ->label('Condition')
                                            ->required()
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ]),

            ]);
    }
}