<?php

namespace App\Filament\Resources\CampPrices;

use App\Models\CampPrice;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\CampPrices\Pages;
use App\Filament\Resources\CampPrices\Schemas\CampPriceForm;
use App\Filament\Resources\CampPrices\Schemas\CampPriceInfolist;
use App\Filament\Resources\CampPrices\Tables\CampPricesTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;
use App\Filament\Clusters\Portfolio\PortfolioCluster;

class CampPriceResource extends Resource
{
    protected static ?string $cluster = PortfolioCluster::class;
    protected static ?int $navigationSort = 1;
    protected static string|UnitEnum|null $navigationGroup = 'Camp Management';
    protected static ?string $model = CampPrice::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return CampPriceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CampPriceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CampPricesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCampPrices::route('/'),
            'create' => Pages\CreateCampPrice::route('/create'),
            'edit' => Pages\EditCampPrice::route('/{record}/edit'),
            'view' => Pages\ViewCampPrice::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}

