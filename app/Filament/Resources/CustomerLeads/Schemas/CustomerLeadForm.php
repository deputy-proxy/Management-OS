<?php

namespace App\Filament\Resources\CustomerLeads\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class CustomerLeadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('first_name')
                    ->required(),
                Forms\Components\TextInput::make('middle_name')
                    ->required(),
                Forms\Components\TextInput::make('last_name')
                    ->required(),
                Forms\Components\TextInput::make('full_name')
                    ->required(),
                Forms\Components\TextInput::make('email')
                    ->required(),
                Forms\Components\TextInput::make('phone')
                    ->required(),
                Forms\Components\TextInput::make('source')
                    ->required(),
                Forms\Components\TextInput::make('topic_interest')
                    ->required(),
                Forms\Components\TextInput::make('product_interest')
                    ->required(),
                Forms\Components\TextInput::make('status')
                    ->required(),
                Forms\Components\DateTimePicker::make('last_contacted_at')
                    ->required(),
            ]);
    }
}
