<?php

namespace App\Filament\Resources\Vendors\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
class VendorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\Select::make('vendor_category_id')
                    ->relationship('vendorCategory', 'title')
                    ->required(),
                Forms\Components\DatePicker::make('start_date')
                    ->required(),
                Forms\Components\DatePicker::make('end_date'),
                Forms\Components\TextInput::make('website')
                    ->required()
            ]);
    }
}
