<?php

namespace App\Filament\Resources\JobDescriptions\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

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
                Forms\Components\KeyValue::make('relevant_indicators')
                    ->required(),
                Forms\Components\TextInput::make('document_code')
                    ->required(),
                Forms\Components\TextInput::make('document')
                    ->required(),
            ]);
    }
}
