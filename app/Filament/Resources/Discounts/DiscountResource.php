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

class DiscountResource extends Resource
{
    protected static ?string $model = Discount::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}

