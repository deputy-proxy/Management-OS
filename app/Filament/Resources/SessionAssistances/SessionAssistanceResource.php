<?php

namespace App\Filament\Resources\SessionAssistances;

use App\Models\SessionAssistance;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\SessionAssistances\Pages;
use App\Filament\Resources\SessionAssistances\Schemas\SessionAssistanceForm;
use App\Filament\Resources\SessionAssistances\Schemas\SessionAssistanceInfolist;
use App\Filament\Resources\SessionAssistances\Tables\SessionAssistancesTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use UnitEnum;
use App\Filament\Clusters\Academic\AcademicCluster;

class SessionAssistanceResource extends Resource
{
    protected static ?int $navigationSort = 6;
    protected static ?string $cluster = AcademicCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Students Management';
    use HasHexaLite;

    protected static ?string $model = SessionAssistance::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function defineGates(): array
    {
        return [
            'session_assistance.index' => __('Allows viewing the session assistance list'),
            'session_assistance.create' => __('Allows creating a new session assistance'),
            'session_assistance.update' => __('Allows updating session assistances'),
            'session_assistance.delete' => __('Allows deleting session assistances'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return SessionAssistanceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SessionAssistanceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SessionAssistancesTable::configure($table);
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
            'index' => Pages\ListSessionAssistances::route('/'),
            'create' => Pages\CreateSessionAssistance::route('/create'),
            'edit' => Pages\EditSessionAssistance::route('/{record}/edit'),
            'view' => Pages\ViewSessionAssistance::route('/{record}'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('session_assistance.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('session_assistance.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create session assistance.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('session_assistance.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('session_assistance.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a session assistance.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('session_assistance.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete session assistances.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
