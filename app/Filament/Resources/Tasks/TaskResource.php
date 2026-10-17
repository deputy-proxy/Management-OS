<?php

namespace App\Filament\Resources\Tasks;

use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use App\Filament\Clusters\Operations\OperationsCluster;
use App\Filament\Resources\Tasks\Schemas\TaskForm;
use App\Filament\Resources\Tasks\Schemas\TaskInfolist;
use App\Filament\Resources\Tasks\Tables\TasksTable;
use App\Models\Task;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Hexters\HexaLite\HasHexaLite;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class TaskResource extends Resource
{
    protected static ?int $navigationSort = 3;

    use HasHexaLite;

    protected static ?string $cluster = OperationsCluster::class;

    protected static string|UnitEnum|null $navigationGroup = 'Project Management';

    protected static ?string $model = Task::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function defineGates(): array
    {
        return [
            'task.index' => __('Allows viewing the task list'),
            'task.view_all' => __('Allows viewing ALL tasks, not just own'),
            'task.create' => __('Allows creating a new task'),
            'task.update' => __('Allows updating tasks'),
            'task.delete' => __('Allows deleting tasks'),
            'task.assign' => __('Allows assigning tasks to other users'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        if (! hexa()->can('task.index')) {
            return null;
        }

        $query = Task::query()->where('status', '!=', 'done');
        if (! hexa()->can('task.view_all')) {
            $userId = auth()->id();
            $query->where(function (Builder $scope) use ($userId): void {
                $scope->where('assigned_to', $userId)->orWhere('created_by', $userId);
            });
        }

        $count = $query->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Open tasks in your permitted task scope';
    }

    public static function form(Schema $schema): Schema
    {
        return TaskForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TaskInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TasksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [ActivitiesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTasks::route('/'),
            'create' => Pages\CreateTask::route('/create'),
            'edit' => Pages\EditTask::route('/{record}/edit'),
            'view' => Pages\ViewTask::route('/{record}'),
        ];
    }

    // ──────────────────────────────────────────────
    // Authorization
    // ──────────────────────────────────────────────

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('task.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('task.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create a task.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        if (! hexa()->can('task.update')) {
            return Response::deny(__('You do not have permission to edit tasks.'));
        }

        // "All" roles can edit any task
        if (hexa()->can('task.view_all')) {
            return Response::allow();
        }

        // "Own" roles can only edit tasks assigned to them
        $user = auth()->user();
        if ((int) $record->assigned_to === $user->id || (int) $record->created_by === $user->id) {
            return Response::allow();
        }

        return Response::deny(__('You can only edit your own tasks.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        if (! hexa()->can('task.delete')) {
            return Response::deny(__('You do not have permission to delete tasks.'));
        }

        if (hexa()->can('task.view_all')) {
            return Response::allow();
        }

        $user = auth()->user();
        if ((int) $record->assigned_to === $user->id || (int) $record->created_by === $user->id) {
            return Response::allow();
        }

        return Response::deny(__('You can only delete your own tasks.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        // Bulk delete only for "all" roles
        return hexa()->can('task.delete') && hexa()->can('task.view_all')
            ? Response::allow()
            : Response::deny(__('You do not have permission to bulk-delete tasks.'));
    }

    // ──────────────────────────────────────────────
    // Query scoping: own vs all
    // ──────────────────────────────────────────────

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);

        // Roles with task.view_all see everything
        if (hexa()->can('task.view_all')) {
            return $query;
        }

        // Everyone else sees only tasks where they are the assignee or creator
        $user = auth()->user();

        return $query->where(function (Builder $q) use ($user) {
            $q->where('assigned_to', $user->id)
                ->orWhere('created_by', $user->id);
        });
    }
}