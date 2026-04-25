<?php

namespace App\Filament\Resources\ProductCosts;

use App\Models\ProductCost;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\ProductCosts\Pages;
use App\Filament\Resources\ProductCosts\Schemas\ProductCostForm;
use App\Filament\Resources\ProductCosts\Schemas\ProductCostInfolist;
use App\Filament\Resources\ProductCosts\Tables\ProductCostsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;
use App\Filament\Clusters\Portfolio\PortfolioCluster;

class ProductCostResource extends Resource
{
    protected static ?string $cluster = PortfolioCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Overview';
    protected static ?string $model = ProductCost::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return ProductCostForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProductCostInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductCostsTable::configure($table);
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
            'index' => Pages\ListProductCosts::route('/'),
            'create' => Pages\CreateProductCost::route('/create'),
            'edit' => Pages\EditProductCost::route('/{record}/edit'),
            'view' => Pages\ViewProductCost::route('/{record}'),
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

