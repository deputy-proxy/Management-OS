<?php

namespace App\Filament\Resources\EventParticipants\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class EventParticipantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('event_id')
                    ->relationship('event', 'title')
                    ->required(),
                Forms\Components\Select::make('customer_id')
                    ->relationship('customer', 'name')
                    ->required(),
            ]);
    }
}
