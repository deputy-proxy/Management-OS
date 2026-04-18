<?php

namespace App\Filament\Resources\VendorCategories;

use App\Models\VendorCategory;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\VendorCategories\Pages;
use App\Filament\Resources\VendorCategories\Schemas\VendorCategoryForm;
use App\Filament\Resources\VendorCategories\Schemas\VendorCategoryInfolist;
use App\Filament\Resources\VendorCategories\Tables\VendorCategoriesTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use UnitEnum;
use App\Filament\Clusters\Finance\FinanceCluster;

class VendorCategoryResource extends Resource
{
    protected static ?int $navigationSort = 1;
    protected static ?string $cluster = FinanceCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Vendor Management';
    use HasHexaLite;

    protected static ?string $model = VendorCategory::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function defineGates(): array
    {
        return [
            'vendor_category.index' => __('Allows viewing the vendor category list'),
            'vendor_category.create' => __('Allows creating a new vendor category'),
            'vendor_category.update' => __('Allows updating vendor categorys'),
            'vendor_category.delete' => __('Allows deleting vendor categorys'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return VendorCategoryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return VendorCategoryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VendorCategoriesTable::configure($table);
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
            'index' => Pages\ListVendorCategories::route('/'),
            'create' => Pages\CreateVendorCategory::route('/create'),
            'edit' => Pages\EditVendorCategory::route('/{record}/edit'),
            'view' => Pages\ViewVendorCategory::route('/{record}'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('vendor_category.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('vendor_category.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create vendor category.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('vendor_category.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('vendor_category.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a vendor category.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('vendor_category.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete vendor categorys.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
