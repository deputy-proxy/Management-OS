<?php

namespace App\Filament\Resources\Products;

use App\Models\Product;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\Products\Pages;
use App\Filament\Resources\Products\Schemas\ProductForm;
use App\Filament\Resources\Products\Schemas\ProductInfolist;
use App\Filament\Resources\Products\Tables\ProductsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;
use App\Filament\Clusters\Portfolio\PortfolioCluster;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;

class ProductResource extends Resource
{
    protected static ?int $navigationSort = 8;
    protected static ?string $cluster = PortfolioCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Product Management';
    protected static ?string $model = Product::class;

    protected static ?string $recordTitleAttribute = 'title';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';
    use HasHexaLite;
    public static function form(Schema $schema): Schema
    {
        return ProductForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProductInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductsTable::configure($table);
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
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
            'view' => Pages\ViewProduct::route('/{record}'),
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
        return ['product.index' => __('Allows viewing the product list'), 'product.create' => __('Allows creating a new product'), 'product.update' => __('Allows updating products'), 'product.delete' => __('Allows deleting products')];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('product.index') ? Response::allow() : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('product.create') ? Response::allow() : Response::deny(__('You do not have permission to create a product.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('product.update') ? Response::allow() : Response::deny(__('You do not have permission to edit this product.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('product.delete') ? Response::allow() : Response::deny(__('You do not have permission to delete this product.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('product.delete') ? Response::allow() : Response::deny(__('You do not have permission to delete products.'));
    }
}



