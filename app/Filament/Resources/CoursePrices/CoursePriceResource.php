<?php

namespace App\Filament\Resources\CoursePrices;

use App\Models\CoursePrice;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\CoursePrices\Pages;
use App\Filament\Resources\CoursePrices\Schemas\CoursePriceForm;
use App\Filament\Resources\CoursePrices\Schemas\CoursePriceInfolist;
use App\Filament\Resources\CoursePrices\Tables\CoursePricesTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CoursePriceResource extends Resource
{
    protected static ?string $model = CoursePrice::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return CoursePriceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CoursePriceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CoursePricesTable::configure($table);
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
            'index' => Pages\ListCoursePrices::route('/'),
            'create' => Pages\CreateCoursePrice::route('/create'),
            'edit' => Pages\EditCoursePrice::route('/{record}/edit'),
            'view' => Pages\ViewCoursePrice::route('/{record}'),
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

