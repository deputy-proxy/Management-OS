<?php

namespace App\Filament\Resources\ProductPrices;

use App\Models\ProductPrice;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\ProductPrices\Pages;
use App\Filament\Resources\ProductPrices\Schemas\ProductPriceForm;
use App\Filament\Resources\ProductPrices\Schemas\ProductPriceInfolist;
use App\Filament\Resources\ProductPrices\Tables\ProductPricesTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;
use App\Filament\Clusters\Portfolio\PortfolioCluster;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;

class ProductPriceResource extends Resource
{
    protected static ?int $navigationSort = 10;
    protected static ?string $cluster = PortfolioCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Product Management';
    protected static ?string $model = ProductPrice::class;

    protected static ?string $recordTitleAttribute = 'title';
    use HasHexaLite;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return ProductPriceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProductPriceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductPricesTable::configure($table);
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
            'index' => Pages\ListProductPrices::route('/'),
            'create' => Pages\CreateProductPrice::route('/create'),
            'edit' => Pages\EditProductPrice::route('/{record}/edit'),
            'view' => Pages\ViewProductPrice::route('/{record}'),
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
        return ['product_price.index' => __('Allows viewing the product price list'), 'product_price.create' => __('Allows creating a new product price'), 'product_price.update' => __('Allows updating product prices'), 'product_price.delete' => __('Allows deleting product prices')];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('product_price.index') ? Response::allow() : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('product_price.create') ? Response::allow() : Response::deny(__('You do not have permission to create product price.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('product_price.update') ? Response::allow() : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('product_price.delete') ? Response::allow() : Response::deny(__('You do not have permission to delete this product price.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('product_price.delete') ? Response::allow() : Response::deny(__('You do not have permission to delete product prices.'));
    }
}



