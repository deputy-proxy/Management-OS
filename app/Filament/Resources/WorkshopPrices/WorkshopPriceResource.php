<?php

namespace App\Filament\Resources\WorkshopPrices;

use App\Models\WorkshopPrice;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\WorkshopPrices\Pages;
use App\Filament\Resources\WorkshopPrices\Schemas\WorkshopPriceForm;
use App\Filament\Resources\WorkshopPrices\Schemas\WorkshopPriceInfolist;
use App\Filament\Resources\WorkshopPrices\Tables\WorkshopPricesTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class WorkshopPriceResource extends Resource
{
    protected static ?string $model = WorkshopPrice::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return WorkshopPriceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WorkshopPriceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkshopPricesTable::configure($table);
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
            'index' => Pages\ListWorkshopPrices::route('/'),
            'create' => Pages\CreateWorkshopPrice::route('/create'),
            'edit' => Pages\EditWorkshopPrice::route('/{record}/edit'),
            'view' => Pages\ViewWorkshopPrice::route('/{record}'),
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

