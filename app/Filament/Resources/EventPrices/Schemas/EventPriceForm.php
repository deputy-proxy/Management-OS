<?php

namespace App\Filament\Resources\EventPrices\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class EventPriceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('event_id')
                    ->relationship('event', 'title')
                    ->required(),
                Forms\Components\TextInput::make('amount')->numeric()
                    ->required(),
                Forms\Components\TextInput::make('currency')
                    ->required(),
            ]);
    }
}
