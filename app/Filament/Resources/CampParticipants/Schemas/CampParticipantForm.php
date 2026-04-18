<?php

namespace App\Filament\Resources\CampParticipants\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class CampParticipantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('camp_id')
                    ->relationship('camp', 'title')
                    ->required(),
                Forms\Components\Select::make('customer_id')
                    ->relationship('customer', 'name')
                    ->required(),
            ]);
    }
}
