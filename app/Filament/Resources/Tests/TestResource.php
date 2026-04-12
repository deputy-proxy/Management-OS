<?php

namespace App\Filament\Resources\Tests;

use App\Models\Test;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\Tests\Pages;
use App\Filament\Resources\Tests\Schemas\TestForm;
use App\Filament\Resources\Tests\Schemas\TestInfolist;
use App\Filament\Resources\Tests\Tables\TestsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TestResource extends Resource
{
    protected static ?string $model = Test::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return TestForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TestsTable::configure($table);
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
            'index' => Pages\ListTests::route('/'),
            'create' => Pages\CreateTest::route('/create'),
            'edit' => Pages\EditTest::route('/{record}/edit'),
            'view' => Pages\ViewTest::route('/{record}'),
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

