<?php

namespace App\Filament\Resources\MarketIntelligenceMetrics;

use App\Filament\Clusters\Marketing\MarketingCluster;
use App\Filament\Resources\Concerns\AuthorizesMarketIntelligenceConfiguration;
use App\Filament\Resources\MarketIntelligenceMetrics\Schemas\MetricForm;
use App\Filament\Resources\MarketIntelligenceMetrics\Tables\MetricsTable;
use App\Models\MarketIntelligenceMetric;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Hexters\HexaLite\HasHexaLite;
use UnitEnum;

class MarketIntelligenceMetricResource extends Resource
{
    use AuthorizesMarketIntelligenceConfiguration, HasHexaLite { AuthorizesMarketIntelligenceConfiguration::defineGates insteadof HasHexaLite; }

    protected static ?int $navigationSort = 13;

    protected static ?string $cluster = MarketingCluster::class;

    protected static string|UnitEnum|null $navigationGroup = 'Market Intelligence Configuration';

    protected static ?string $model = MarketIntelligenceMetric::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Metric Definitions';

    public static function form(Schema $schema): Schema
    {
        return MetricForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MetricsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMetrics::route('/'),
            'create' => Pages\CreateMetric::route('/create'),
            'edit' => Pages\EditMetric::route('/{record}/edit'),
        ];
    }
}
