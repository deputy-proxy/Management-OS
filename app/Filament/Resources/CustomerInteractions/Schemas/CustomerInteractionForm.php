<?php

namespace App\Filament\Resources\CustomerInteractions\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
class CustomerInteractionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('customer_id')
                    ->relationship('customer', 'name')
                    ->required(),
                Forms\Components\TextInput::make('initiator')
                    ->required(),
                Forms\Components\TextInput::make('reason')
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->required(),
                Forms\Components\DateTimePicker::make('timestamp')
                    ->required(),
                Forms\Components\TextInput::make('interaction_rating')
                    ->numeric()
                    ->required()
            ]);
    }
}
