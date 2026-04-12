<?php

namespace App\Filament\Resources\Competitions;

use App\Models\Competition;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\Competitions\Pages;
use App\Filament\Resources\Competitions\Schemas\CompetitionForm;
use App\Filament\Resources\Competitions\Schemas\CompetitionInfolist;
use App\Filament\Resources\Competitions\Tables\CompetitionsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CompetitionResource extends Resource
{
    protected static ?string $model = Competition::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return CompetitionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CompetitionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CompetitionsTable::configure($table);
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
            'index' => Pages\ListCompetitions::route('/'),
            'create' => Pages\CreateCompetition::route('/create'),
            'edit' => Pages\EditCompetition::route('/{record}/edit'),
            'view' => Pages\ViewCompetition::route('/{record}'),
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

