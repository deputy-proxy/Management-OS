<?php

namespace App\Filament\Resources\CourseMaterials;

use App\Models\CourseMaterial;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\CourseMaterials\Pages;
use App\Filament\Resources\CourseMaterials\Schemas\CourseMaterialForm;
use App\Filament\Resources\CourseMaterials\Schemas\CourseMaterialInfolist;
use App\Filament\Resources\CourseMaterials\Tables\CourseMaterialsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CourseMaterialResource extends Resource
{
    protected static ?string $model = CourseMaterial::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return CourseMaterialForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CourseMaterialInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CourseMaterialsTable::configure($table);
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
            'index' => Pages\ListCourseMaterials::route('/'),
            'create' => Pages\CreateCourseMaterial::route('/create'),
            'edit' => Pages\EditCourseMaterial::route('/{record}/edit'),
            'view' => Pages\ViewCourseMaterial::route('/{record}'),
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

