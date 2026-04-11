<?php

namespace App\Filament\Resources\FinancialAccounts\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
class FinancialAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('title')
                    ->required(),
                Forms\Components\TextInput::make('type')
                    ->required(),
                Forms\Components\TextInput::make('bank'),
                Forms\Components\TextInput::make('currency')
                    ->required(),
                Forms\Components\TextInput::make('status')
                    ->required(),
                Forms\Components\DatePicker::make('opened_on'),
                Forms\Components\DatePicker::make('closed_on')
            ]);
    }
}
