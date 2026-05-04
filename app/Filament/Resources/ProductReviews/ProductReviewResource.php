<?php

namespace App\Filament\Resources\ProductReviews;

use App\Models\ProductReview;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\ProductReviews\Pages;
use App\Filament\Resources\ProductReviews\Schemas\ProductReviewForm;
use App\Filament\Resources\ProductReviews\Schemas\ProductReviewInfolist;
use App\Filament\Resources\ProductReviews\Tables\ProductReviewsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use UnitEnum;
use App\Filament\Clusters\Portfolio\PortfolioCluster;

class ProductReviewResource extends Resource
{
    protected static ?int $navigationSort = 15;
    protected static ?string $cluster = PortfolioCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Product Management';
    use HasHexaLite;
    protected static ?string $model = ProductReview::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function defineGates(): array
    {
        return [
            'product_review.index' => __('Allows viewing the product review list'),
            'product_review.create' => __('Allows creating a new product review'),
            'product_review.update' => __('Allows updating product reviews'),
            'product_review.delete' => __('Allows deleting product reviews'),
        ];
    }
    public static function form(Schema $schema): Schema
    {
        return ProductReviewForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProductReviewInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductReviewsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ActivitiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductReviews::route('/'),
            'create' => Pages\CreateProductReview::route('/create'),
            'edit' => Pages\EditProductReview::route('/{record}/edit'),
            'view' => Pages\ViewProductReview::route('/{record}'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('product_review.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('product_review.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create a product review.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('product_review.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('product_review.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a product review.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('product_review.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete product reviews.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}


