<?php

namespace App\Filament\Resources\ContentTemplates\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class ContentTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('title')
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->required(),
                Forms\Components\TextInput::make('template_for')
                    ->required(),
                Forms\Components\TextInput::make('link')
                    ->required(),
                Forms\Components\DatePicker::make('last_revision')
                    ->required(),
            ]);
    }
}
