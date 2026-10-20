<?php

namespace App\Filament\Resources\MarketTrends\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class MarketTrendForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('category')
                    ->options([
                        'demand' => 'Demand',
                        'search_interest' => 'Search interest',
                        'competition' => 'Competition',
                        'pricing' => 'Pricing',
                        'growth' => 'Growth',
                    ])
                    ->searchable()
                    ->required(),
                Forms\Components\TextInput::make('metric_key')
                    ->required()
                    ->maxLength(100),
                Forms\Components\TextInput::make('value')
                    ->numeric()
                    ->required(),
                Forms\Components\TextInput::make('unit')
                    ->required()
                    ->maxLength(50),
                Forms\Components\TextInput::make('market')
                    ->required()
                    ->maxLength(100),
                Forms\Components\TextInput::make('segment')
                    ->maxLength(100),
                Forms\Components\DatePicker::make('period_start')
                    ->required(),
                Forms\Components\DatePicker::make('period_end')
                    ->required()
                    ->afterOrEqual('period_start'),
                Forms\Components\TextInput::make('source_name')
                    ->required()
                    ->maxLength(100),
                Forms\Components\TextInput::make('source_url')
                    ->url()
                    ->maxLength(2048),
                Forms\Components\DateTimePicker::make('observed_at')
                    ->required(),
                Forms\Components\TextInput::make('previous_value')
                    ->numeric(),
                Forms\Components\TextInput::make('change_pct')
                    ->numeric(),
                Forms\Components\TextInput::make('confidence')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(1),
                Forms\Components\Textarea::make('methodology')
                    ->columnSpanFull(),
                Forms\Components\KeyValue::make('metadata')
                    ->keyLabel('Attribute')
                    ->valueLabel('Value')
                    ->columnSpanFull(),
                Forms\Components\Select::make('status')
                    ->options([
                        'active' => 'Active',
                        'stale' => 'Stale',
                        'invalid' => 'Invalid',
                    ])
                    ->default('active')
                    ->required(),
            ]);
    }
}
