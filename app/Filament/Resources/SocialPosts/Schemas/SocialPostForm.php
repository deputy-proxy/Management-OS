<?php

namespace App\Filament\Resources\SocialPosts\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class SocialPostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('title')
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->required(),
                Forms\Components\TextInput::make('network')
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
