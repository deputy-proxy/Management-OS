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
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;


class ProductResourceAllocationResource extends Resource
{
    protected static ?int $navigationSort = 12;
    protected static ?string $cluster = PortfolioCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Product Management';
    protected static ?string $model = ProductResourceAllocation::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';
    use HasHexaLite;
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
    public function defineGates(): array
    {
        return ['product_resource_allocation.index' => __('Allows viewing the product resource allocation list'), 'product_resource_allocation.create' => __('Allows creating a new product resource allocation'), 'product_resource_allocation.update' => __('Allows updating product resource allocations'), 'product_resource_allocation.delete' => __('Allows deleting product resource allocations')];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('product_resource_allocation.index') ? Response::allow() : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('product_resource_allocation.create') ? Response::allow() : Response::deny(__('You do not have permission to create product resource allocation.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('product_resource_allocation.update') ? Response::allow() : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('product_resource_allocation.delete') ? Response::allow() : Response::deny(__('You do not have permission to delete this product resource allocation.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('product_resource_allocation.delete') ? Response::allow() : Response::deny(__('You do not have permission to delete product resource allocations.'));
    }
}



