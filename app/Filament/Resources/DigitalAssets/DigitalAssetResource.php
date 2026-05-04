<?php

namespace App\Filament\Resources\DigitalAssets;

use App\Models\DigitalAsset;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\DigitalAssets\Pages;
use App\Filament\Resources\DigitalAssets\Schemas\DigitalAssetForm;
use App\Filament\Resources\DigitalAssets\Schemas\DigitalAssetInfolist;
use App\Filament\Resources\DigitalAssets\Tables\DigitalAssetsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;
use App\Filament\Clusters\General\GeneralCluster;

class DigitalAssetResource extends Resource
{
    protected static ?int $navigationSort = 3;
    protected static ?string $cluster = GeneralCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Inventory Management';
    protected static ?string $model = DigitalAsset::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getNavigationBadge(): ?string
    {
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
}

