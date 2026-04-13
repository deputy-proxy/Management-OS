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
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;

class GroupSessionResource extends Resource
{
    use HasHexaLite;

    protected static ?string $model = GroupSession::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function defineGates(): array
    {
        return [
            'group_session.index' => __('Allows viewing the group session list'),
            'group_session.create' => __('Allows creating a new group session'),
            'group_session.update' => __('Allows updating group sessions'),
            'group_session.delete' => __('Allows deleting group sessions'),
        ];
    }

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

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('group_session.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('group_session.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create group session.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('group_session.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('group_session.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a group session.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('group_session.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete group sessions.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}

