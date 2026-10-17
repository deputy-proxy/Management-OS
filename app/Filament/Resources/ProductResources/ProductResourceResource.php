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
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;

class ProductResourceResource extends Resource
{
    protected static ?int $navigationSort = 13;
    protected static ?string $cluster = PortfolioCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Product Management';
    protected static ?string $model = ProductResource::class;

    protected static ?string $recordTitleAttribute = 'title';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';
    use HasHexaLite;
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
    public function defineGates(): array
    {
        return ['product_resource.index' => __('Allows viewing the product resource list'), 'product_resource.create' => __('Allows creating a new product resource'), 'product_resource.update' => __('Allows updating product resources'), 'product_resource.delete' => __('Allows deleting product resources')];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('product_resource.index') ? Response::allow() : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('product_resource.create') ? Response::allow() : Response::deny(__('You do not have permission to create product resource.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('product_resource.update') ? Response::allow() : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('product_resource.delete') ? Response::allow() : Response::deny(__('You do not have permission to delete this product resource.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('product_resource.delete') ? Response::allow() : Response::deny(__('You do not have permission to delete product resources.'));
    }
}



