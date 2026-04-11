<?php

namespace App\Filament\Resources\Taxes\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class TaxForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('category')
                    ->required(),
                Forms\Components\TextInput::make('month')
                    ->required(),
                Forms\Components\TextInput::make('amount')->numeric()
                    ->required(),
                Forms\Components\TextInput::make('status')
                    ->required(),
                Forms\Components\DatePicker::make('due_on')
                    ->required(),
                Forms\Components\DatePicker::make('paid_on')
                    ->required(),
            ]);
    }
}
