<?php

namespace App\Filament\Resources\ProductResources;

use App\Models\ProductResource;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\ProductResources\Pages;
use App\Filament\Resources\ProductResources\Schemas\ProductResourceForm;
use App\Filament\Resources\ProductResources\Schemas\ProductResourceInfolist;
use App\Filament\Resources\ProductResources\Tables\ProductResourcesTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;
use App\Filament\Clusters\Portfolio\PortfolioCluster;
class ProductResourceResource extends Resource
{
    protected static ?int $navigationSort = 20;
    protected static ?string $cluster = PortfolioCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Product Management';
    protected static ?string $model = ProductResource::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return ProductResourceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProductResourceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductResourcesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductResources::route('/'),
            'create' => Pages\CreateProductResource::route('/create'),
            'edit' => Pages\EditProductResource::route('/{record}/edit'),
            'view' => Pages\ViewProductResource::route('/{record}')
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}

