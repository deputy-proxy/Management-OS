<?php

namespace App\Filament\Resources\GoogleAdsImportConfigurations;

use App\Filament\Clusters\Marketing\MarketingCluster;
use App\Filament\Resources\Concerns\AuthorizesMarketIntelligenceConfiguration;
use App\Filament\Resources\GoogleAdsImportConfigurations\Schemas\GoogleAdsImportConfigurationForm;
use App\Filament\Resources\GoogleAdsImportConfigurations\Tables\GoogleAdsImportConfigurationsTable;
use App\Models\GoogleAdsImportConfiguration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Hexters\HexaLite\HasHexaLite;
use UnitEnum;

class GoogleAdsImportConfigurationResource extends Resource
{
    use AuthorizesMarketIntelligenceConfiguration, HasHexaLite { AuthorizesMarketIntelligenceConfiguration::defineGates insteadof HasHexaLite; }

    protected static ?string $model = GoogleAdsImportConfiguration::class;

    protected static ?string $cluster = MarketingCluster::class;

    protected static string|UnitEnum|null $navigationGroup = 'Market Intelligence Configuration';

    protected static ?string $navigationLabel = 'Google Ads Configurations';

    protected static ?int $navigationSort = 14;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-megaphone';

    public static function form(Schema $schema): Schema
    {
        return GoogleAdsImportConfigurationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GoogleAdsImportConfigurationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGoogleAdsImportConfigurations::route('/'),
            'create' => Pages\CreateGoogleAdsImportConfiguration::route('/create'),
            'edit' => Pages\EditGoogleAdsImportConfiguration::route('/{record}/edit'),
        ];
    }
}
