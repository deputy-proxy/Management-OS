<?php

namespace App\Filament\Resources\GroupSessions;

use App\Models\GroupSession;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\GroupSessions\Pages;
use App\Filament\Resources\GroupSessions\Schemas\GroupSessionForm;
use App\Filament\Resources\GroupSessions\Schemas\GroupSessionInfolist;
use App\Filament\Resources\GroupSessions\Tables\GroupSessionsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class GroupSessionResource extends Resource
{
    protected static ?string $model = GroupSession::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return GroupSessionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return GroupSessionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GroupSessionsTable::configure($table);
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
            'index' => Pages\ListGroupSessions::route('/'),
            'create' => Pages\CreateGroupSession::route('/create'),
            'edit' => Pages\EditGroupSession::route('/{record}/edit'),
            'view' => Pages\ViewGroupSession::route('/{record}'),
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

