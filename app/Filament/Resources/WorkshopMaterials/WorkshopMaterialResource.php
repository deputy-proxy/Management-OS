<?php

namespace App\Filament\Resources\WorkshopMaterials;

use App\Models\WorkshopMaterial;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\WorkshopMaterials\Pages;
use App\Filament\Resources\WorkshopMaterials\Schemas\WorkshopMaterialForm;
use App\Filament\Resources\WorkshopMaterials\Schemas\WorkshopMaterialInfolist;
use App\Filament\Resources\WorkshopMaterials\Tables\WorkshopMaterialsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class WorkshopMaterialResource extends Resource
{
    protected static ?string $model = WorkshopMaterial::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return WorkshopMaterialForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WorkshopMaterialInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkshopMaterialsTable::configure($table);
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
            'index' => Pages\ListWorkshopMaterials::route('/'),
            'create' => Pages\CreateWorkshopMaterial::route('/create'),
            'edit' => Pages\EditWorkshopMaterial::route('/{record}/edit'),
            'view' => Pages\ViewWorkshopMaterial::route('/{record}'),
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

