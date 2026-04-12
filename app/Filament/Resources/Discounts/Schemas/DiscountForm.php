<?php

namespace App\Filament\Resources\Discounts\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class DiscountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('applies_to_type')
                    ->required(),
                Forms\Components\TextInput::make('applies_to_item')->numeric()
                    ->required(),
                Forms\Components\TextInput::make('value')->numeric()
                    ->required(),
                Forms\Components\TextInput::make('type')
                    ->required(),
                Forms\Components\DatePicker::make('start_on')
                    ->required(),
                Forms\Components\DatePicker::make('end_on')
                    ->required(),
            ]);
    }
}
