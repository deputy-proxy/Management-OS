<?php
namespace App\Filament\Resources\Customers\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Information')
                    ->description('Information about the customer.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required(),
                        Select::make('customer_type')
                            ->required()
                            ->options([
                                'Individual' => 'Individual',
                                'Company' => 'Company',
                            ]),
                        Forms\Components\TextInput::make('cnp_cui')
                            ->label('CNP/CUI')
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