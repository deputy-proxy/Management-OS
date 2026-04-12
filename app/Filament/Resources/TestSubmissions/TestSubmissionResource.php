<?php

namespace App\Filament\Resources\TestSubmissions;

use App\Models\TestSubmission;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\TestSubmissions\Pages;
use App\Filament\Resources\TestSubmissions\Schemas\TestSubmissionForm;
use App\Filament\Resources\TestSubmissions\Schemas\TestSubmissionInfolist;
use App\Filament\Resources\TestSubmissions\Tables\TestSubmissionsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TestSubmissionResource extends Resource
{
    protected static ?string $model = TestSubmission::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return TestSubmissionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TestSubmissionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TestSubmissionsTable::configure($table);
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
            'index' => Pages\ListTestSubmissions::route('/'),
            'create' => Pages\CreateTestSubmission::route('/create'),
            'edit' => Pages\EditTestSubmission::route('/{record}/edit'),
            'view' => Pages\ViewTestSubmission::route('/{record}'),
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

