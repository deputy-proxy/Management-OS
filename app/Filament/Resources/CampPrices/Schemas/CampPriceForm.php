<?php

namespace App\Filament\Resources\CampPrices\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class CampPriceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('camp_id')
                    ->relationship('camp', 'title')
                    ->required(),
                Forms\Components\TextInput::make('amount')->numeric()
                    ->required(),
                Forms\Components\TextInput::make('currency')
                    ->required(),
                Forms\Components\DatePicker::make('start_on')
                    ->required(),
                Forms\Components\DatePicker::make('end_on')
                    ->required(),
            ]);
    }
}
