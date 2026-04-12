<?php

namespace App\Filament\Resources\PlacementTests;

use App\Models\PlacementTest;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\PlacementTests\Pages;
use App\Filament\Resources\PlacementTests\Schemas\PlacementTestForm;
use App\Filament\Resources\PlacementTests\Schemas\PlacementTestInfolist;
use App\Filament\Resources\PlacementTests\Tables\PlacementTestsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PlacementTestResource extends Resource
{
    protected static ?string $model = PlacementTest::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return PlacementTestForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PlacementTestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlacementTestsTable::configure($table);
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
            'index' => Pages\ListPlacementTests::route('/'),
            'create' => Pages\CreatePlacementTest::route('/create'),
            'edit' => Pages\EditPlacementTest::route('/{record}/edit'),
            'view' => Pages\ViewPlacementTest::route('/{record}'),
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

