<?php
namespace App\Filament\Resources\CustomerContracts\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class CustomerContractForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Contract Reference')
                    ->description('The unique code and associated customer for this contract.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('customer_id')
                            ->relationship('customer', 'name')
                            ->required(),
                        Forms\Components\TextInput::make('contract_code')
                            ->required(),
                    ]),

                Section::make('Contract Period')
                    ->description('The start and end dates defining the validity of this contract.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\DatePicker::make('start_date')
                            ->required(),
                        Forms\Components\DatePicker::make('end_date')
                            ->required(),
                    ]),

                Section::make('Documentation')
                    ->description('Supporting document attached to this contract.')
                    ->columns(1)
                    ->schema([
                        Forms\Components\TextInput::make('document')
                            ->required(),
                    ]),
            ]);
    }
}