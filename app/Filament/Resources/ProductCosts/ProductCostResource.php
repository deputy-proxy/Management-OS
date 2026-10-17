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
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
class ProductCostResource extends Resource
{
    protected static ?int $navigationSort = 11;
    protected static ?string $cluster = PortfolioCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Product Management';
    protected static ?string $model = ProductCost::class;

    protected static ?string $recordTitleAttribute = 'title';
    use HasHexaLite;
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
    public function defineGates(): array
    {
        return ['product_cost.index' => __('Allows viewing the product cost list'), 'product_cost.create' => __('Allows creating a new product cost'), 'product_cost.update' => __('Allows updating product costs'), 'product_cost.delete' => __('Allows deleting product costs')];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('product_cost.index') ? Response::allow() : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('product_cost.create') ? Response::allow() : Response::deny(__('You do not have permission to create product cost.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('product_cost.update') ? Response::allow() : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('product_cost.delete') ? Response::allow() : Response::deny(__('You do not have permission to delete this product cost.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('product_cost.delete') ? Response::allow() : Response::deny(__('You do not have permission to delete product costs.'));
    }
}





