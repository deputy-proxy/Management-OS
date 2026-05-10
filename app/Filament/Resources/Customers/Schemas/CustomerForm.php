<?php
namespace App\Filament\Resources\Customers\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Information')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required(),
                        Forms\Components\TextInput::make('customer_type')
                            ->required(),
                        Forms\Components\TextInput::make('cnp_cui')
                            ->required(),
                    ]),

                Section::make('Contact Details')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('address')
                            ->required(),
                        Forms\Components\TextInput::make('email')
                            ->required()
                            ->email(),
                    ]),
            ]);
    }
}