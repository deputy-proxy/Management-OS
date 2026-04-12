<?php

namespace App\Filament\Resources\Articles\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class ArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('title')
                    ->required(),
                Forms\Components\Textarea::make('content')
                    ->required(),
                Forms\Components\TextInput::make('published_in')
                    ->required(),
                Forms\Components\DatePicker::make('scheduled_on')
                    ->required(),
                Forms\Components\DatePicker::make('published_on')
                    ->required(),
                Forms\Components\TextInput::make('status')
                    ->required(),
            ]);
    }
}
