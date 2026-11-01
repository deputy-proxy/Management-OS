<?php

namespace App\Filament\Resources\MarketIntelligenceSegments;

use App\Filament\Clusters\Marketing\MarketingCluster;
use App\Filament\Resources\Concerns\AuthorizesMarketIntelligenceConfiguration;
use App\Filament\Resources\MarketIntelligenceSegments\Schemas\SegmentForm;
use App\Filament\Resources\MarketIntelligenceSegments\Tables\SegmentsTable;
use App\Models\MarketIntelligenceSegment;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Hexters\HexaLite\HasHexaLite;
use UnitEnum;

class MarketIntelligenceSegmentResource extends Resource
{
    use AuthorizesMarketIntelligenceConfiguration, HasHexaLite { AuthorizesMarketIntelligenceConfiguration::defineGates insteadof HasHexaLite; }

    protected static ?int $navigationSort = 12;

    protected static ?string $cluster = MarketingCluster::class;

    protected static string|UnitEnum|null $navigationGroup = 'Market Intelligence Configuration';

    protected static ?string $model = MarketIntelligenceSegment::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Market Segments';

    public static function form(Schema $schema): Schema
    {
        return SegmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SegmentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSegments::route('/'),
            'create' => Pages\CreateSegment::route('/create'),
            'edit' => Pages\EditSegment::route('/{record}/edit'),
        ];
    }
}
