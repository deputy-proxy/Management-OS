<?php

namespace App\Filament\Resources\Discounts;

use App\Models\Discount;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\Discounts\Pages;
use App\Filament\Resources\Discounts\Schemas\DiscountForm;
use App\Filament\Resources\Discounts\Schemas\DiscountInfolist;
use App\Filament\Resources\Discounts\Tables\DiscountsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use UnitEnum;
use App\Filament\Clusters\Products\ProductsCluster;

class DiscountResource extends Resource
{
    protected static ?string $cluster = ProductsCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Overview';
    use HasHexaLite;

    protected static ?string $model = Discount::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function defineGates(): array
    {
        return [
            'discount.index' => __('Allows viewing the discount list'),
            'discount.create' => __('Allows creating a new discount'),
            'discount.update' => __('Allows updating discounts'),
            'discount.delete' => __('Allows deleting discounts'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return DiscountForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DiscountInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DiscountsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ActivitiesRelationManager::class,
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDiscounts::route('/'),
            'create' => Pages\CreateDiscount::route('/create'),
            'edit' => Pages\EditDiscount::route('/{record}/edit'),
            'view' => Pages\ViewDiscount::route('/{record}'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('discount.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('discount.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create discount.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('discount.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('discount.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a discount.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('discount.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete discounts.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
