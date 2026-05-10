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
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;

class ProductRevenueResource extends Resource
{
    protected static ?int $navigationSort = 14;
    protected static ?string $cluster = PortfolioCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Product Management';
    protected static ?string $model = ProductRevenue::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';
    use HasHexaLite;
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
    public function defineGates(): array
    {
        return ['product_revenue.index' => __('Allows viewing the product revenue list'), 'product_revenue.create' => __('Allows creating a new product revenue'), 'product_revenue.update' => __('Allows updating product revenues'), 'product_revenue.delete' => __('Allows deleting product revenues')];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('product_revenue.index') ? Response::allow() : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('product_revenue.create') ? Response::allow() : Response::deny(__('You do not have permission to create product revenue.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('product_revenue.update') ? Response::allow() : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('product_revenue.delete') ? Response::allow() : Response::deny(__('You do not have permission to delete this product revenue.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('product_revenue.delete') ? Response::allow() : Response::deny(__('You do not have permission to delete product revenues.'));
    }
}



