<?php

namespace App\Filament\Resources\JobDescriptions\Schemas;

use Filament\Forms;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class JobDescriptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('job_title')
                    ->required(),
                TextInput::make('job_level')
                    ->required(),
                Forms\Components\Textarea::make('job_role')
                    ->required(),
                Select::make('line_manager_id')
                    ->label('Line Manager Job Description')
                    ->relationship('lineManager', 'job_title')
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->helperText('Select the job description this role reports to. Self-management and circular reporting chains are rejected.'),
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
                TextInput::make('document_code')
                    ->required(),
                TextInput::make('document')
                    ->required(),
            ]);
    }
}
