<?php

namespace App\Filament\Resources\QuarterlyObjectives;

use App\Models\QuarterlyObjective;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\QuarterlyObjectives\Pages;
use App\Filament\Resources\QuarterlyObjectives\Schemas\QuarterlyObjectiveForm;
use App\Filament\Resources\QuarterlyObjectives\Schemas\QuarterlyObjectiveInfolist;
use App\Filament\Resources\QuarterlyObjectives\Tables\QuarterlyObjectivesTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use UnitEnum;
use App\Filament\Clusters\General\GeneralCluster;

class QuarterlyObjectiveResource extends Resource
{
    protected static ?int $navigationSort = 11;
    protected static ?string $cluster = GeneralCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Human Resources';
    use HasHexaLite;
    protected static ?string $model = QuarterlyObjective::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function defineGates(): array
    {
        return [
            'quarterly_objective.index' => __('Allows viewing the quarterly objective list'),
            'quarterly_objective.create' => __('Allows creating a new quarterly objective'),
            'quarterly_objective.update' => __('Allows updating quarterly objectives'),
            'quarterly_objective.delete' => __('Allows deleting quarterly objectives'),
        ];
    }
    public static function form(Schema $schema): Schema
    {
        return QuarterlyObjectiveForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return QuarterlyObjectiveInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QuarterlyObjectivesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ActivitiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQuarterlyObjectives::route('/'),
            'create' => Pages\CreateQuarterlyObjective::route('/create'),
            'edit' => Pages\EditQuarterlyObjective::route('/{record}/edit'),
            'view' => Pages\ViewQuarterlyObjective::route('/{record}'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('quarterly_objective.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('quarterly_objective.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create a quarterly objective.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('quarterly_objective.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('quarterly_objective.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a quarterly objective.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('quarterly_objective.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete quarterly objectives.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}


