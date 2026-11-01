<?php

namespace App\Filament\Resources\MarketIntelligenceMarkets;

use App\Filament\Clusters\Marketing\MarketingCluster;
use App\Filament\Resources\Concerns\AuthorizesMarketIntelligenceConfiguration;
use App\Filament\Resources\MarketIntelligenceMarkets\Schemas\MarketForm;
use App\Filament\Resources\MarketIntelligenceMarkets\Tables\MarketsTable;
use App\Models\MarketIntelligenceMarket;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Hexters\HexaLite\HasHexaLite;
use UnitEnum;

class MarketIntelligenceMarketResource extends Resource
{
    use AuthorizesMarketIntelligenceConfiguration, HasHexaLite { AuthorizesMarketIntelligenceConfiguration::defineGates insteadof HasHexaLite; }

    protected static ?int $navigationSort = 11;

    protected static ?string $cluster = MarketingCluster::class;

    protected static string|UnitEnum|null $navigationGroup = 'Market Intelligence Configuration';

    protected static ?string $model = MarketIntelligenceMarket::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationLabel = 'Tracked Markets';

    public static function form(Schema $schema): Schema
    {
        return MarketForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MarketsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMarkets::route('/'),
            'create' => Pages\CreateMarket::route('/create'),
            'edit' => Pages\EditMarket::route('/{record}/edit'),
        ];
    }
}
