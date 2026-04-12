<?php

namespace App\Filament\Resources\Newsletters\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class NewsletterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('title')
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->required(),
                Forms\Components\TextInput::make('audience')
                    ->required(),
                Forms\Components\Textarea::make('content')
                    ->required(),
                Forms\Components\DatePicker::make('scheduled_on')
                    ->required(),
                Forms\Components\DatePicker::make('delivered_on')
                    ->required(),
                Forms\Components\TextInput::make('status')
                    ->required(),
            ]);
    }
}
