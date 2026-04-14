<?php

namespace App\Filament\Resources\WorkshopParticipants\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class WorkshopParticipantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('workshop_id')
                    ->relationship('workshop', 'title')
                    ->required(),
                Forms\Components\Select::make('customer_id')
                    ->relationship('customer', 'name')
                    ->required(),
            ]);
    }
}
