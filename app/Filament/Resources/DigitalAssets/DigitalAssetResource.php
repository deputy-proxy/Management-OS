<?php

namespace App\Filament\Resources\DigitalAssets;

use App\Filament\Clusters\General\GeneralCluster;
use App\Filament\Resources\DigitalAssets\Schemas\DigitalAssetForm;
use App\Filament\Resources\DigitalAssets\Schemas\DigitalAssetInfolist;
use App\Filament\Resources\DigitalAssets\Tables\DigitalAssetsTable;
use App\Models\DigitalAsset;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Hexters\HexaLite\HasHexaLite;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class DigitalAssetResource extends Resource
{
    protected static ?int $navigationSort = 3;

    protected static ?string $cluster = GeneralCluster::class;

    protected static string|UnitEnum|null $navigationGroup = 'Inventory Management';

    protected static ?string $model = DigitalAsset::class;

    protected static ?string $recordTitleAttribute = 'title';

    use HasHexaLite;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getNavigationBadge(): ?string
    {
        if (! hexa()->can('digital_asset.index')) {
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
        return 'Number of digital assets';
    }

    public static function form(Schema $schema): Schema
    {
        return DigitalAssetForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DigitalAssetInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DigitalAssetsTable::configure($table);
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
            'index' => Pages\ListDigitalAssets::route('/'),
            'create' => Pages\CreateDigitalAsset::route('/create'),
            'edit' => Pages\EditDigitalAsset::route('/{record}/edit'),
            'view' => Pages\ViewDigitalAsset::route('/{record}'),
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
        return ['digital_asset.index' => __('Allows viewing the digital asset list'), 'digital_asset.create' => __('Allows creating a new digital asset'), 'digital_asset.update' => __('Allows updating digital assets'), 'digital_asset.delete' => __('Allows deleting digital assets')];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('digital_asset.index') ? Response::allow() : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('digital_asset.create') ? Response::allow() : Response::deny(__('You do not have permission to create digital asset.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('digital_asset.update') ? Response::allow() : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('digital_asset.delete') ? Response::allow() : Response::deny(__('You do not have permission to delete a digital asset.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('digital_asset.delete') ? Response::allow() : Response::deny(__('You do not have permission to delete digital assets.'));
    }
}