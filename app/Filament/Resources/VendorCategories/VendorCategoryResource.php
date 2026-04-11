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

class VendorCategoryResource extends Resource
{
    protected static ?string $model = VendorCategory::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}

