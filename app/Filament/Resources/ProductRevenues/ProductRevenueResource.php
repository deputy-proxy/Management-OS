<?php

namespace App\Filament\Resources\ProductRevenues;

use App\Models\ProductRevenue;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\ProductRevenues\Pages;
use App\Filament\Resources\ProductRevenues\Schemas\ProductRevenueForm;
use App\Filament\Resources\ProductRevenues\Schemas\ProductRevenueInfolist;
use App\Filament\Resources\ProductRevenues\Tables\ProductRevenuesTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;
use App\Filament\Clusters\Portfolio\PortfolioCluster;

class ProductRevenueResource extends Resource
{
    protected static ?int $navigationSort = 21;
    protected static ?string $cluster = PortfolioCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Product Management';
    protected static ?string $model = ProductRevenue::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return ProductRevenueForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProductRevenueInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductRevenuesTable::configure($table);
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
            'index' => Pages\ListProductRevenues::route('/'),
            'create' => Pages\CreateProductRevenue::route('/create'),
            'edit' => Pages\EditProductRevenue::route('/{record}/edit'),
            'view' => Pages\ViewProductRevenue::route('/{record}'),
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

