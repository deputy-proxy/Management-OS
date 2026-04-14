<?php

namespace App\Filament\Resources\NewsletterSubscribers\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class NewsletterSubscriberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('first_name')
                    ->required(),
                Forms\Components\TextInput::make('last_name')
                    ->required(),
                Forms\Components\TextInput::make('email')
                    ->required(),
                Forms\Components\Select::make('customer_id')
                    ->relationship('customer', 'name'),
            ]);
    }
}
