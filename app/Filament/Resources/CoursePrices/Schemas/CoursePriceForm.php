<?php

namespace App\Filament\Resources\CoursePrices\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class CoursePriceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('course_id')
                    ->relationship('course', 'title')
                    ->required(),
                Forms\Components\TextInput::make('amount')->numeric()
                    ->required(),
                Forms\Components\DatePicker::make('start_on')
                    ->required(),
                Forms\Components\DatePicker::make('end_on')
                    ->required(),
            ]);
    }
}
