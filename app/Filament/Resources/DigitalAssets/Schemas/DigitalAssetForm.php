<?php
namespace App\Filament\Resources\DigitalAssets\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;

class DigitalAssetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Asset Details')
                    ->description('General information about this digital asset.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->columnSpanFull(),
                        Select::make('type')
                            ->required()
                            ->options([
                                'Video' => 'Video',
                                'Document' => 'Document',
                                'Service Subscription' => 'Service Subscription',
                            ]),
                        Forms\Components\Select::make('vendor_id')
                            ->relationship('vendor', 'name')
                            ->required(),
                        Select::make('status')
                            ->required()
                            ->options([
                                'Active' => 'Active',
                                'Inactive' => 'Inactive',
                            ]),
                        Forms\Components\DatePicker::make('registered_on')
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Access Credentials')
                    ->description('Login details used to access this asset.')
                    ->schema([
                        Forms\Components\TextInput::make('email_used')
                            ->required(),
                        Forms\Components\TextInput::make('username')
                            ->required(),
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

            ]);
    }
}