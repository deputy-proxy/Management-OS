<?php

namespace App\Filament\Resources\PhysicalAssets;

use App\Models\PhysicalAsset;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\PhysicalAssets\Pages;
use App\Filament\Resources\PhysicalAssets\Schemas\PhysicalAssetForm;
use App\Filament\Resources\PhysicalAssets\Schemas\PhysicalAssetInfolist;
use App\Filament\Resources\PhysicalAssets\Tables\PhysicalAssetsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;
use App\Filament\Clusters\General\GeneralCluster;

class PhysicalAssetResource extends Resource
{
    protected static ?int $navigationSort = 4;
    protected static ?string $cluster = GeneralCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Inventory Management';
    protected static ?string $model = PhysicalAsset::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return PhysicalAssetForm::configure($schema);
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
}

