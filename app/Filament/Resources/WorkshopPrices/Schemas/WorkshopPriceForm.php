<?php

namespace App\Filament\Resources\WorkshopPrices\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class WorkshopPriceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('workshop_id')
                    ->relationship('workshop', 'title')
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
