<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\TextInput::make('customer_type')
                    ->required(),
                Forms\Components\TextInput::make('cnp_cui')
                    ->required(),
                Forms\Components\TextInput::make('address')
                    ->required(),
                Forms\Components\TextInput::make('email')
                    ->required(),
            ]);
    }
}
