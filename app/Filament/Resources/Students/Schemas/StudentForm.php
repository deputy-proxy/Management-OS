<?php

namespace App\Filament\Resources\Students\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class StudentForm
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
                Forms\Components\TextInput::make('gender')
                    ->required(),
                Forms\Components\DatePicker::make('date_of_birth')
                    ->required(),
                Forms\Components\Select::make('customer_id')
                    ->relationship('customer', 'name')
                    ->required(),
                Forms\Components\TextInput::make('phone_number')
                    ->required(),
                Forms\Components\TextInput::make('email')
                    ->required(),
                Forms\Components\TextInput::make('address')
                    ->required(),
            ]);
    }
}
