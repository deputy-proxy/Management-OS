<?php

namespace App\Filament\Resources\ProductResourceAllocations;

use App\Models\ProductResourceAllocation;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\ProductResourceAllocations\Pages;
use App\Filament\Resources\ProductResourceAllocations\Schemas\ProductResourceAllocationForm;
use App\Filament\Resources\ProductResourceAllocations\Schemas\ProductResourceAllocationInfolist;
use App\Filament\Resources\ProductResourceAllocations\Tables\ProductResourceAllocationsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;
use App\Filament\Clusters\Portfolio\PortfolioCluster;

class ProductResourceAllocationResource extends Resource
{
    protected static ?string $cluster = PortfolioCluster::class;
    protected static ?int $navigationSort = 5;
    protected static string|UnitEnum|null $navigationGroup = 'Overview';
    protected static ?string $model = ProductResourceAllocation::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return ProductResourceAllocationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProductResourceAllocationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductResourceAllocationsTable::configure($table);
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
            'index' => Pages\ListProductResourceAllocations::route('/'),
            'create' => Pages\CreateProductResourceAllocation::route('/create'),
            'edit' => Pages\EditProductResourceAllocation::route('/{record}/edit'),
            'view' => Pages\ViewProductResourceAllocation::route('/{record}'),
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

