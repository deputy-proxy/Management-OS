<?php

namespace App\Filament\Resources\PhysicalAssets\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class PhysicalAssetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\TextInput::make('asset_tag')
                    ->required()
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('serial_number')
                    ->required(),
                Forms\Components\TextInput::make('category')
                    ->required(),
                Forms\Components\TextInput::make('location')
                    ->required(),
                Forms\Components\TextInput::make('status')
                    ->required(),
                Forms\Components\TextInput::make('condition')
                    ->required(),
                Forms\Components\DatePicker::make('purchase_date')
                    ->required(),
                Forms\Components\TextInput::make('purchase_price')->numeric()
                    ->required(),
                Forms\Components\DatePicker::make('warranty_expiry_date')
                    ->required(),
            ]);
    }
}
