<?php

namespace App\Filament\Resources\MarketTrends;

use App\Filament\Clusters\Marketing\MarketingCluster;
use App\Filament\Resources\MarketTrends\Pages;
use App\Filament\Resources\MarketTrends\Schemas\MarketTrendForm;
use App\Filament\Resources\MarketTrends\Tables\MarketTrendsTable;
use App\Models\MarketTrend;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Hexters\HexaLite\HasHexaLite;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class MarketTrendResource extends Resource
{
    use HasHexaLite;

    protected static ?int $navigationSort = 10;

    protected static ?string $cluster = MarketingCluster::class;

    protected static string|UnitEnum|null $navigationGroup = 'Market Analysis';

    protected static ?string $model = MarketTrend::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Market Trends';

    public function defineGates(): array
    {
        return [
            'market_trend.index' => __('Allows viewing market trends'),
            'market_trend.create' => __('Allows creating market trends'),
            'market_trend.update' => __('Allows updating market trends'),
            'market_trend.delete' => __('Allows deleting market trends'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return MarketTrendForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MarketTrendsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMarketTrends::route('/'),
            'create' => Pages\CreateMarketTrend::route('/create'),
            'view' => Pages\ViewMarketTrend::route('/{record}'),
            'edit' => Pages\EditMarketTrend::route('/{record}/edit'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('market_trend.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('market_trend.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create market trends.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('market_trend.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit market trends.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('market_trend.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete market trends.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('market_trend.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete market trends.'));
    }
}
