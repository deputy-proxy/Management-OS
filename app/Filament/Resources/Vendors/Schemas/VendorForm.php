<?php
namespace App\Filament\Resources\Vendors\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class VendorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Vendor Information')
                    ->description('Core details identifying this vendor and their category.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->columnSpanFull(),
                        Forms\Components\Select::make('vendor_category_id')
                            ->relationship('vendorCategory', 'title')
                            ->required(),
                        Forms\Components\TextInput::make('website')
                            ->required(),
                    ]),

                Section::make('Engagement Period')
                    ->description('The start and end dates of the relationship with this vendor.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\DatePicker::make('start_date')
                            ->required(),
                        Forms\Components\DatePicker::make('end_date'),
                    ]),
            ]);
    }
}