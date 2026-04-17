<?php

namespace App\Filament\Resources\Competitors\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

class CompetitorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\TextInput::make('site')
                    ->required(),
                Forms\Components\TextInput::make('instagram')
                    ->required(),
                Forms\Components\TextInput::make('facebook')
                    ->required(),
                Forms\Components\TextInput::make('linkedin')
                    ->required(),
                Forms\Components\TextInput::make('tiktok')
                    ->required(),
                Builder::make('locations')
                    ->blocks([
                        Block::make('location')
                            ->schema([
                                TextInput::make('address')
                                    ->label('Address')
                                    ->required()
                                    ->columnSpan(1),
                                TextInput::make('coordinates')
                                    ->label('Coordinates')
                                    ->required()
                                    ->columnSpan(1),
                            ])
                            ->columns(2)
                    ])
                    
                    ->required(),
            ]);
    }
}
