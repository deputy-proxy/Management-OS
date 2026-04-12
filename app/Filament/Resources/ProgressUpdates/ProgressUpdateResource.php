<?php

namespace App\Filament\Resources\ProgressUpdates;

use App\Models\ProgressUpdate;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\ProgressUpdates\Pages;
use App\Filament\Resources\ProgressUpdates\Schemas\ProgressUpdateForm;
use App\Filament\Resources\ProgressUpdates\Schemas\ProgressUpdateInfolist;
use App\Filament\Resources\ProgressUpdates\Tables\ProgressUpdatesTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProgressUpdateResource extends Resource
{
    protected static ?string $model = ProgressUpdate::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return ProgressUpdateForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProgressUpdateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProgressUpdatesTable::configure($table);
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
            'index' => Pages\ListProgressUpdates::route('/'),
            'create' => Pages\CreateProgressUpdate::route('/create'),
            'edit' => Pages\EditProgressUpdate::route('/{record}/edit'),
            'view' => Pages\ViewProgressUpdate::route('/{record}'),
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

