<?php

namespace App\Filament\Resources\MarketIntelligenceMarkets\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class MarketForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('code')->required()->maxLength(40)->regex('/^[A-Z0-9][A-Z0-9._-]{0,39}$/')->helperText('Stable uppercase geography code, for example RO. Provisional defaults are not approved business scope.'),
            Forms\Components\TextInput::make('name')->required()->maxLength(120),
            Forms\Components\Select::make('geography_type')->options(['country' => 'Country', 'region' => 'Region', 'city' => 'City', 'custom' => 'Custom', 'unspecified' => 'Unspecified'])->required()->default('unspecified'),
            Forms\Components\Textarea::make('description')->columnSpanFull(),
            Forms\Components\Toggle::make('is_active')->default(true),
            Forms\Components\Toggle::make('is_provisional')->default(true)->helperText('Keep enabled until a business owner confirms this definition.'),
        ]);
    }
}
