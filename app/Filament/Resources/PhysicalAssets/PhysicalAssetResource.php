<?php

namespace App\Filament\Resources\PhysicalAssets;

use App\Filament\Clusters\General\GeneralCluster;
use App\Filament\Resources\PhysicalAssets\Schemas\PhysicalAssetForm;
use App\Filament\Resources\PhysicalAssets\Schemas\PhysicalAssetInfolist;
use App\Filament\Resources\PhysicalAssets\Tables\PhysicalAssetsTable;
use App\Models\PhysicalAsset;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Hexters\HexaLite\HasHexaLite;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class PhysicalAssetResource extends Resource
{
    protected static ?int $navigationSort = 4;

    protected static ?string $cluster = GeneralCluster::class;

    protected static string|UnitEnum|null $navigationGroup = 'Inventory Management';

    protected static ?string $model = PhysicalAsset::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    use HasHexaLite;

    public static function form(Schema $schema): Schema
    {
        return PhysicalAssetForm::configure($schema);
    }

    public static function getNavigationBadge(): ?string
    {
        if (! hexa()->can('physical_asset.index')) {
            return null;
        }

        return static::getModel()::count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Number of physical assets';
    }

    public static function infolist(Schema $schema): Schema
    {
        return PhysicalAssetInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PhysicalAssetsTable::configure($table);
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
            'index' => Pages\ListPhysicalAssets::route('/'),
            'create' => Pages\CreatePhysicalAsset::route('/create'),
            'edit' => Pages\EditPhysicalAsset::route('/{record}/edit'),
            'view' => Pages\ViewPhysicalAsset::route('/{record}'),
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
        return ['physical_asset.index' => __('Allows viewing the physical asset list'), 'physical_asset.create' => __('Allows creating a new physical asset'), 'physical_asset.update' => __('Allows updating physical assets'), 'physical_asset.delete' => __('Allows deleting physical assets')];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('physical_asset.index') ? Response::allow() : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('physical_asset.create') ? Response::allow() : Response::deny(__('You do not have permission to create physical asset.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('physical_asset.update') ? Response::allow() : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('physical_asset.delete') ? Response::allow() : Response::deny(__('You do not have permission to delete a physical asset.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('physical_asset.delete') ? Response::allow() : Response::deny(__('You do not have permission to delete physical assets.'));
    }
}