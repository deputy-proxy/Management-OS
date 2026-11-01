<?php

namespace App\Filament\Resources\MarketIntelligenceMetrics\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class MetricForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('key')->required()->maxLength(100)->regex('/^[a-z][a-z0-9_]{0,99}$/')->helperText('Stable machine-readable key. Existing observation keys remain supported.'),
            Forms\Components\TextInput::make('name')->required()->maxLength(120),
            Forms\Components\Select::make('category')->options(['demand' => 'Demand', 'search_interest' => 'Search interest', 'competition' => 'Competition', 'pricing' => 'Pricing', 'growth' => 'Growth'])->required()->searchable(),
            Forms\Components\Select::make('value_type')->options(['index' => 'Index', 'currency' => 'Currency', 'count' => 'Count', 'percentage' => 'Percentage', 'ratio' => 'Ratio', 'duration' => 'Duration', 'quantity' => 'Quantity', 'other' => 'Other'])->required(),
            Forms\Components\TagsInput::make('allowed_units')->required()->rules(['array', 'min:1'])->helperText('Exact allowed unit codes or labels. No implicit unit conversion is performed.'),
            Forms\Components\Select::make('period_granularity')->options(['day' => 'Day', 'week' => 'Week', 'month' => 'Month', 'quarter' => 'Quarter', 'year' => 'Year', 'custom' => 'Custom'])->required()->default('custom'),
            Forms\Components\TextInput::make('max_period_days')->numeric()->integer()->minValue(1)->maxValue(36525)->helperText('Optional maximum inclusive period length.'),
            Forms\Components\Textarea::make('description')->columnSpanFull(),
            Forms\Components\Toggle::make('is_active')->default(true),
            Forms\Components\Toggle::make('is_provisional')->default(true)->helperText('Keep enabled until a business owner confirms this definition.'),
        ]);
    }
}
