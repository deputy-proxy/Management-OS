<?php

namespace App\Filament\Resources\ProjectPhases;

use App\Models\ProjectPhase;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\ProjectPhases\Pages;
use App\Filament\Resources\ProjectPhases\Schemas\ProjectPhaseForm;
use App\Filament\Resources\ProjectPhases\Schemas\ProjectPhaseInfolist;
use App\Filament\Resources\ProjectPhases\Tables\ProjectPhasesTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use UnitEnum;
use App\Filament\Clusters\Operations\OperationsCluster;

class ProjectPhaseResource extends Resource
{
    protected static ?int $navigationSort = 5;
    protected static ?string $cluster = OperationsCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Project Management';
    use HasHexaLite;

    protected static ?string $model = ProjectPhase::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function defineGates(): array
    {
        return [
            'project_phase.index' => __('Allows viewing the project phase list'),
            'project_phase.create' => __('Allows creating a new project phase'),
            'project_phase.update' => __('Allows updating project phases'),
            'project_phase.delete' => __('Allows deleting project phases'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return ProjectPhaseForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProjectPhaseInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProjectPhasesTable::configure($table);
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
            'index' => Pages\ListProjectPhases::route('/'),
            'create' => Pages\CreateProjectPhase::route('/create'),
            'edit' => Pages\EditProjectPhase::route('/{record}/edit'),
            'view' => Pages\ViewProjectPhase::route('/{record}'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('project_phase.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('project_phase.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create project phase.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('project_phase.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('project_phase.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a project phase.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('project_phase.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete project phases.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
