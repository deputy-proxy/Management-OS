<?php

namespace App\Filament\Resources\EventPrices;

use App\Models\EventPrice;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\EventPrices\Pages;
use App\Filament\Resources\EventPrices\Schemas\EventPriceForm;
use App\Filament\Resources\EventPrices\Schemas\EventPriceInfolist;
use App\Filament\Resources\EventPrices\Tables\EventPricesTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EventPriceResource extends Resource
{
    protected static ?string $model = EventPrice::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return EventPriceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EventPriceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EventPricesTable::configure($table);
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
            'index' => Pages\ListEventPrices::route('/'),
            'create' => Pages\CreateEventPrice::route('/create'),
            'edit' => Pages\EditEventPrice::route('/{record}/edit'),
            'view' => Pages\ViewEventPrice::route('/{record}'),
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

