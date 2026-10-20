<?php

namespace App\Filament\Resources\Camps\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class CampForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('title')
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->required(),
                Forms\Components\TextInput::make('location')
                    ->required(),
                Forms\Components\DatePicker::make('start_on')
                    ->required(),
                Forms\Components\DatePicker::make('end_on')
                    ->required(),
            ]);
    }
}
