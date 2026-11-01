<?php

namespace App\Filament\Resources\MarketTrends\Schemas;

use App\Models\MarketIntelligenceMarket;
use App\Models\MarketIntelligenceMetric;
use App\Models\MarketIntelligenceSegment;
use Filament\Forms;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class MarketTrendForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\Select::make('category')
                ->options([
                    'demand' => 'Demand',
                    'search_interest' => 'Search interest',
                    'competition' => 'Competition',
                    'pricing' => 'Pricing',
                    'growth' => 'Growth',
                ])->searchable()->required(),
            Forms\Components\Select::make('market_intelligence_metric_id')
                ->label('Metric definition')
                ->options(fn (): array => MarketIntelligenceMetric::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()->live()
                ->afterStateUpdated(function ($state, Set $set): void {
                    $metric = $state ? MarketIntelligenceMetric::query()->find($state) : null;
                    if ($metric) {
                        $set('metric_key', $metric->key);
                        $set('category', $metric->category);
                        if (count($metric->allowed_units ?? []) === 1) {
                            $set('unit', $metric->allowed_units[0]);
                        }
                    }
                })
                ->helperText('Optional for legacy observations. A selected definition enforces unit and period compatibility.'),
            Forms\Components\TextInput::make('metric_key')->required()->maxLength(100),
            Forms\Components\Select::make('data_status')
                ->options(['present' => 'Value present', 'missing' => 'Value missing'])
                ->default('present')->live()->required()
                ->afterStateUpdated(function ($state, Set $set): void {
                    if ($state === 'missing') {
                        $set('value', null);
                        $set('confidence', null);
                    } else {
                        $set('missing_reason', null);
                    }
                }),
            Forms\Components\TextInput::make('value')
                ->numeric()->required(fn (Get $get): bool => $get('data_status') === 'present'),
            Forms\Components\Textarea::make('missing_reason')
                ->visible(fn (Get $get): bool => $get('data_status') === 'missing')
                ->required(fn (Get $get): bool => $get('data_status') === 'missing')
                ->columnSpanFull(),
            Forms\Components\TextInput::make('unit')
                ->required()->maxLength(50)
                ->helperText(fn (Get $get): string => ($metric = MarketIntelligenceMetric::query()->where('key', $get('metric_key'))->first())
                    ? 'Allowed units: '.implode(', ', $metric->allowed_units ?? [])
                    : 'No configured metric definition; unit compatibility is unknown.'),
            Forms\Components\Select::make('market_intelligence_market_id')
                ->label('Tracked market definition')
                ->options(fn (): array => MarketIntelligenceMarket::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()->live()
                ->afterStateUpdated(fn ($state, Set $set) => $set('market', (string) (MarketIntelligenceMarket::query()->whereKey($state)->value('code') ?? '')))
                ->helperText('Optional. Existing free-text market values remain supported.'),
            Forms\Components\TextInput::make('market')->required()->maxLength(100),
            Forms\Components\Select::make('market_intelligence_segment_id')
                ->label('Tracked segment definition')
                ->options(fn (): array => MarketIntelligenceSegment::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()->live()
                ->afterStateUpdated(fn ($state, Set $set) => $set('segment', MarketIntelligenceSegment::query()->whereKey($state)->value('code')))
                ->helperText('Optional. Existing free-text segment values remain supported.'),
            Forms\Components\TextInput::make('segment')->maxLength(100),
            Forms\Components\DatePicker::make('period_start')->required(),
            Forms\Components\DatePicker::make('period_end')->required()->afterOrEqual('period_start'),
            Forms\Components\TextInput::make('source_name')->required()->maxLength(100),
            Forms\Components\TextInput::make('source_url')->url()->maxLength(2048),
            Forms\Components\TextInput::make('source_reference')->maxLength(255)
                ->helperText('Stable source/provider record identifier. Required for imported observations.'),
            Forms\Components\TextInput::make('source_revision')->maxLength(100),
            Forms\Components\DateTimePicker::make('observed_at')->required(),
            Forms\Components\TextInput::make('previous_value')->numeric(),
            Forms\Components\TextInput::make('change_pct')->numeric(),
            Forms\Components\TextInput::make('confidence')->numeric()->minValue(0)->maxValue(1)
                ->helperText('Optional confidence value from 0 to 1; it is not a verification status.'),
            Forms\Components\Textarea::make('methodology')->columnSpanFull(),
            Forms\Components\KeyValue::make('metadata')->keyLabel('Attribute')->valueLabel('Value')->columnSpanFull(),
            Forms\Components\KeyValue::make('source_metadata')->keyLabel('Source attribute')->valueLabel('Value')->columnSpanFull(),
            Forms\Components\Select::make('source_type')
                ->options(['unknown' => 'Unknown', 'manual' => 'Manual', 'imported' => 'Imported', 'synthetic' => 'Synthetic'])
                ->default('unknown')->required(),
            Forms\Components\Select::make('freshness_status')
                ->options(['unknown' => 'Unknown', 'current' => 'Current', 'stale' => 'Stale'])
                ->default('unknown')->required(),
            Forms\Components\Select::make('verification_status')
                ->options(['unverified' => 'Unverified', 'verified' => 'Verified'])
                ->default('unverified')->required()
                ->helperText('Verification is independent of freshness and source type.'),
            Forms\Components\Select::make('status')
                ->options(['active' => 'Active', 'stale' => 'Legacy stale', 'invalid' => 'Invalid'])
                ->default('active')->required(),
        ]);
    }
}
