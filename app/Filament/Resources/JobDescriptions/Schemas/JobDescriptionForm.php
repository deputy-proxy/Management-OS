<?php

namespace App\Filament\Resources\JobDescriptions\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

class JobDescriptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('job_title')
                    ->required(),
                Forms\Components\TextInput::make('job_level')
                    ->required(),
                Forms\Components\Textarea::make('job_role')
                    ->required(),
                Builder::make('relevant_indicators')
                    ->blocks([
                        Block::make('relevant_indicator')
                            ->schema([
                                TextInput::make('indicator')
                                    ->label('Indicator')
                                    ->required()
                                    ->columnSpan(3),
                                TextInput::make('value')
                                    ->label('Value')
                                    ->required()
                                    ->columnSpan(1),
                            ])
                            ->columns(4),
                    ])
                    ->columns(1),
                Forms\Components\TextInput::make('document_code')
                    ->required(),
                Forms\Components\TextInput::make('document')
                    ->required(),
            ]);
    }
}
