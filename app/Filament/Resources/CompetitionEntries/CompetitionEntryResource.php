<?php

namespace App\Filament\Resources\CompetitionEntries;

use App\Models\CompetitionEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\CompetitionEntries\Pages;
use App\Filament\Resources\CompetitionEntries\Schemas\CompetitionEntryForm;
use App\Filament\Resources\CompetitionEntries\Schemas\CompetitionEntryInfolist;
use App\Filament\Resources\CompetitionEntries\Tables\CompetitionEntriesTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CompetitionEntryResource extends Resource
{
    protected static ?string $model = CompetitionEntry::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return CompetitionEntryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CompetitionEntryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CompetitionEntriesTable::configure($table);
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
            'index' => Pages\ListCompetitionEntries::route('/'),
            'create' => Pages\CreateCompetitionEntry::route('/create'),
            'edit' => Pages\EditCompetitionEntry::route('/{record}/edit'),
            'view' => Pages\ViewCompetitionEntry::route('/{record}'),
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

