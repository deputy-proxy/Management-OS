<?php

namespace App\Filament\Resources\IndividualSessions;

use App\Models\IndividualSession;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\IndividualSessions\Pages;
use App\Filament\Resources\IndividualSessions\Schemas\IndividualSessionForm;
use App\Filament\Resources\IndividualSessions\Schemas\IndividualSessionInfolist;
use App\Filament\Resources\IndividualSessions\Tables\IndividualSessionsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
class IndividualSessionResource extends Resource
{
    protected static ?string $model = IndividualSession::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return IndividualSessionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return IndividualSessionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IndividualSessionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIndividualSessions::route('/'),
            'create' => Pages\CreateIndividualSession::route('/create'),
            'edit' => Pages\EditIndividualSession::route('/{record}/edit'),
            'view' => Pages\ViewIndividualSession::route('/{record}')
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}

