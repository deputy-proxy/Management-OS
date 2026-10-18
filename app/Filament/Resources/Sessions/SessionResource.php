<?php

namespace App\Filament\Resources\Sessions;

use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use App\Filament\Clusters\Academic\AcademicCluster;
use App\Filament\Resources\Sessions\Schemas\SessionForm;
use App\Filament\Resources\Sessions\Schemas\SessionInfolist;
use App\Filament\Resources\Sessions\Tables\SessionsTable;
use App\Models\Group;
use App\Models\Session;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Hexters\HexaLite\HasHexaLite;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class SessionResource extends Resource
{
    protected static ?int $navigationSort = 4;

    protected static ?string $cluster = AcademicCluster::class;

    protected static string|UnitEnum|null $navigationGroup = 'Students Management';

    use HasHexaLite;

    protected static ?string $model = Session::class;

    protected static ?string $recordTitleAttribute = 'identifier';

    protected static ?string $modelLabel = 'Session';

    protected static ?string $pluralModelLabel = 'Sessions';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function defineGates(): array
    {
        return ['session.index' => __('Allows viewing sessions'), 'session.create' => __('Allows creating sessions'), 'session.update' => __('Allows updating sessions'), 'session.delete' => __('Allows deleting sessions')];
    }

    public static function form(Schema $schema): Schema
    {
        return SessionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SessionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SessionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [ActivitiesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSessions::route('/'),
            'create' => Pages\CreateSession::route('/create'),
            'edit' => Pages\EditSession::route('/{record}/edit'),
            'view' => Pages\ViewSession::route('/{record}'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return (hexa()->can('session.index') || hexa()->can('group_session.index') || hexa()->can('individual_session.index')) ? Response::allow() : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return (hexa()->can('session.create') || hexa()->can('group_session.create') || hexa()->can('individual_session.create')) ? Response::allow() : Response::deny(__('You do not have permission to create a session.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return static::canManageType($record, 'update') ? Response::allow() : Response::deny(__('You do not have permission to edit this session.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return static::canManageType($record, 'delete') ? Response::allow() : Response::deny(__('You do not have permission to delete a session.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        $canDeleteGroup = hexa()->can('group_session.delete');
        $canDeleteIndividual = hexa()->can('individual_session.delete');
        $canViewGroupOnly = hexa()->can('group_session.index') && ! hexa()->can('individual_session.index');
        $canViewIndividualOnly = hexa()->can('individual_session.index') && ! hexa()->can('group_session.index');
        $canDeleteVisibleTypes = ($canDeleteGroup && $canViewGroupOnly) || ($canDeleteIndividual && $canViewIndividualOnly);

        return (hexa()->can('session.delete') || ($canDeleteGroup && $canDeleteIndividual) || $canDeleteVisibleTypes)
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete these sessions.'));
    }

    public static function validateGroupAssignment(array $data): void
    {
        if (($data['session_type'] ?? null) !== Session::TYPE_GROUP) {
            return;
        }

        $group = isset($data['group_id']) ? Group::query()->find($data['group_id']) : null;
        if ($group === null) {
            throw ValidationException::withMessages(['group_id' => 'Choose an existing group.']);
        }

        if ((int) $group->course_id !== (int) ($data['course_id'] ?? 0)) {
            throw ValidationException::withMessages(['course_id' => 'A group session must use the course assigned to its group.']);
        }

        if ($group->teacher_id === null || (int) $group->teacher_id !== (int) ($data['teacher_id'] ?? 0)) {
            throw ValidationException::withMessages(['teacher_id' => 'A group session must use the teacher assigned to its group. Assign a teacher to the group first.']);
        }
    }

    public static function canCreateType(?string $type): bool
    {
        if (hexa()->can('session.create')) {
            return in_array($type, [Session::TYPE_GROUP, Session::TYPE_INDIVIDUAL], true);
        }

        return match ($type) {
            Session::TYPE_GROUP => hexa()->can('group_session.create'),
            Session::TYPE_INDIVIDUAL => hexa()->can('individual_session.create'),
            default => false,
        };
    }

    private static function canManageType(Model $record, string $operation): bool
    {
        if (hexa()->can('session.'.$operation)) {
            return true;
        }

        $legacyAbility = $record->session_type === Session::TYPE_GROUP
            ? 'group_session.'.$operation
            : 'individual_session.'.$operation;

        return hexa()->can($legacyAbility);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);

        if (hexa()->can('session.index')) {
            return $query;
        }

        $canViewGroup = hexa()->can('group_session.index');
        $canViewIndividual = hexa()->can('individual_session.index');

        if ($canViewGroup && ! $canViewIndividual) {
            return $query->where('session_type', Session::TYPE_GROUP);
        }

        if ($canViewIndividual && ! $canViewGroup) {
            return $query->where('session_type', Session::TYPE_INDIVIDUAL);
        }

        return $query->whereRaw('1 = 0');
    }
}
