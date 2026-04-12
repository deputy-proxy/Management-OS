<?php

namespace App\Filament\Resources\TestResults;

use App\Models\TestResult;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\TestResults\Pages;
use App\Filament\Resources\TestResults\Schemas\TestResultForm;
use App\Filament\Resources\TestResults\Schemas\TestResultInfolist;
use App\Filament\Resources\TestResults\Tables\TestResultsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TestResultResource extends Resource
{
    protected static ?string $model = TestResult::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return TestResultForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TestResultInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TestResultsTable::configure($table);
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
            'index' => Pages\ListTestResults::route('/'),
            'create' => Pages\CreateTestResult::route('/create'),
            'edit' => Pages\EditTestResult::route('/{record}/edit'),
            'view' => Pages\ViewTestResult::route('/{record}'),
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

