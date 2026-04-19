<?php

namespace App\Filament\Resources\EmployeeEvaluations;

use App\Models\EmployeeEvaluation;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\EmployeeEvaluations\Pages;
use App\Filament\Resources\EmployeeEvaluations\Schemas\EmployeeEvaluationForm;
use App\Filament\Resources\EmployeeEvaluations\Schemas\EmployeeEvaluationInfolist;
use App\Filament\Resources\EmployeeEvaluations\Tables\EmployeeEvaluationsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
use App\Filament\Clusters\HumanResources\HumanResourcesCluster;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use UnitEnum;
use App\Filament\Clusters\General\GeneralCluster;
class EmployeeEvaluationResource extends Resource
{
    protected static ?int $navigationSort = 3;
    protected static ?string $cluster = GeneralCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Human Resources';
    use HasHexaLite;

    protected static ?string $model = EmployeeEvaluation::class;
    //protected static ?string $cluster = HumanResourcesCluster::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';
    public function defineGates(): array
    {
        return ['employee_evaluation.index' => __('Allows viewing the employee evaluation list'), 'employee_evaluation.create' => __('Allows creating a new employee evaluation'), 'employee_evaluation.update' => __('Allows updating employee evaluations'), 'employee_evaluation.delete' => __('Allows deleting employee evaluations')];
    }

    public static function form(Schema $schema): Schema
    {
        return EmployeeEvaluationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EmployeeEvaluationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeeEvaluationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [ActivitiesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployeeEvaluations::route('/'),
            'create' => Pages\CreateEmployeeEvaluation::route('/create'),
            'edit' => Pages\EditEmployeeEvaluation::route('/{record}/edit'),
            'view' => Pages\ViewEmployeeEvaluation::route('/{record}')
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('employee_evaluation.index') ? Response::allow() : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('employee_evaluation.create') ? Response::allow() : Response::deny(__('You do not have permission to create employee evaluation.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('employee_evaluation.update') ? Response::allow() : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('employee_evaluation.delete') ? Response::allow() : Response::deny(__('You do not have permission to delete a employee evaluation.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('employee_evaluation.delete') ? Response::allow() : Response::deny(__('You do not have permission to delete employee evaluations.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
