<?php

namespace App\Filament\Resources\Processes\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
class ProcessForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('title')
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->required(),
                Forms\Components\Select::make('owner_id')
                    ->relationship('owner', 'full_name')
                    ->required(),
                Forms\Components\TextInput::make('main_topic')
                    ->required(),
                
                Builder::make('steps')
                    ->blocks([
                        Block::make('step')
                            ->schema([
                                TextInput::make('title')
                                    ->label('Title')
                                    ->required(),
                                Select::make('actor')
                                    ->options([
                                        'h1' => 'Heading 1',
                                        'h2' => 'Heading 2',
                                        'h3' => 'Heading 3',
                                        'h4' => 'Heading 4',
                                        'h5' => 'Heading 5',
                                        'h6' => 'Heading 6',
                                    ])
                                    ->required(),
                                Textarea::make('content')
                                    ->label('Description')
                                    ->required(),
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
                                    ->required(),
                            ]),
                    ]),
                
            ]);
    }
}
