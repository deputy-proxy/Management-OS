<?php
namespace App\Filament\Resources\PhysicalAssets\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;

class PhysicalAssetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Asset Identity')
                    ->description('Identifying information for this physical asset.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('asset_tag')
                            ->required()
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('serial_number')
                            ->required(),
                        Select::make('category')
                            ->required()
                            ->options([
                                'Computers' => 'Computers',
                                'Furniture' => 'Furniture',
                                'Equipment' => 'Equipment',
                            ]),
                    ])
                    ->columns(2),

                Section::make('Location & Condition')
                    ->description('Where this asset is and its current state.')
                    ->schema([
                        Forms\Components\TextInput::make('location')
                            ->required(),
                        Select::make('status')
                            ->required()
                            ->options([
                                'Operational' => 'Operational',
                                'In Maintenance' => 'In Maintenance',
                                'Retired' => 'Retired',
                            ]),
                        Select::make('condition')
                            ->required()
                            ->options([
                                'Excellent' => 'Excellent',
                                'Good' => 'Good',
                                'Fair' => 'Fair',
                                'Poor' => 'Poor',
                            ]),
                    ])
                    ->columns(2),

                Section::make('Purchase & Warranty')
                    ->description('Financial and warranty information for this asset.')
                    ->schema([
                        Forms\Components\DatePicker::make('purchase_date')
                            ->required(),
                        Forms\Components\TextInput::make('purchase_price')
                            ->numeric()
                            ->required(),
                        Forms\Components\DatePicker::make('warranty_expiry_date')
                            ->required(),
                    ])
                    ->columns(2),

            ]);
    }
}