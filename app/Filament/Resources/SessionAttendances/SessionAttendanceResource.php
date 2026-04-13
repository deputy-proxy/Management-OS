<?php

namespace App\Filament\Resources\SessionAttendances;

use App\Models\SessionAttendance;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\SessionAttendances\Pages;
use App\Filament\Resources\SessionAttendances\Schemas\SessionAttendanceForm;
use App\Filament\Resources\SessionAttendances\Schemas\SessionAttendanceInfolist;
use App\Filament\Resources\SessionAttendances\Tables\SessionAttendancesTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;

class SessionAttendanceResource extends Resource
{
    use HasHexaLite;

    protected static ?string $model = SessionAttendance::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function defineGates(): array
    {
        return [
            'session_attendance.index' => __('Allows viewing the session attendance list'),
            'session_attendance.create' => __('Allows creating a new session attendance'),
            'session_attendance.update' => __('Allows updating session attendances'),
            'session_attendance.delete' => __('Allows deleting session attendances'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return SessionAttendanceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SessionAttendanceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SessionAttendancesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ActivitiesRelationManager::class,
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSessionAttendances::route('/'),
            'create' => Pages\CreateSessionAttendance::route('/create'),
            'edit' => Pages\EditSessionAttendance::route('/{record}/edit'),
            'view' => Pages\ViewSessionAttendance::route('/{record}'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('session_attendance.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('session_attendance.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create session attendance.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('session_attendance.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('session_attendance.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a session attendance.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('session_attendance.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete session attendances.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
