<?php

namespace App\Filament\Resources\Partners\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;

class PartnerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\TextInput::make('site')
                    ->required(),
                Forms\Components\TextInput::make('contact_person_name')
                    ->required(),
                Forms\Components\TextInput::make('contact_person_email')
                    ->required(),
                Forms\Components\TextInput::make('contact_person_phone')
                    ->required(),
                Builder::make('collaborations')
                    ->blocks([
                        Block::make('collaboration')
                            ->schema([
                                DatePicker::make('date')
                                    ->label('Date')
                                    ->required()
                                    ->columnSpan(1),
                                Textarea::make('description')
                                    ->label('Description')
                                    ->required()
                                    ->columnSpan(3),
                            ])
                            ->columns(4)
                    ])
                    ->required(),
            ]);
    }
}
