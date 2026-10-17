<?php

namespace App\Filament\Resources\EmployeeEvaluationForms;

use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use App\Filament\Clusters\General\GeneralCluster;
use App\Filament\Clusters\HumanResources\HumanResourcesCluster;
use App\Filament\Resources\EmployeeEvaluationForms\Schemas\EmployeeEvaluationFormForm;
use App\Filament\Resources\EmployeeEvaluationForms\Schemas\EmployeeEvaluationFormInfolist;
use App\Filament\Resources\EmployeeEvaluationForms\Tables\EmployeeEvaluationFormsTable;
use App\Models\EmployeeEvaluationForm;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Hexters\HexaLite\HasHexaLite;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class EmployeeEvaluationFormResource extends Resource
{
    protected static ?int $navigationSort = 8;

    protected static ?string $cluster = GeneralCluster::class;

    protected static string|UnitEnum|null $navigationGroup = 'Human Resources';

    use HasHexaLite;

    protected static ?string $model = EmployeeEvaluationForm::class;
    // protected static ?string $cluster = HumanResourcesCluster::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getNavigationBadge(): ?string
    {
        if (! hexa()->can('employee_evaluation_form.index')) {
            return null;
        }

        return static::getModel()::count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Number of employee evaluation forms';
    }

    public function defineGates(): array
    {
        return [
            ActivitiesRelationManager::class,
            'employee_evaluation_form.index' => __('Allows viewing the employee evaluation form list'),
            'employee_evaluation_form.create' => __('Allows creating a new employee evaluation form'),
            'employee_evaluation_form.update' => __('Allows updating employee evaluation forms'),
            'employee_evaluation_form.delete' => __('Allows deleting employee evaluation forms'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return EmployeeEvaluationFormForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EmployeeEvaluationFormInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeeEvaluationFormsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployeeEvaluationForms::route('/'),
            'create' => Pages\CreateEmployeeEvaluationForm::route('/create'),
            'edit' => Pages\EditEmployeeEvaluationForm::route('/{record}/edit'),
            'view' => Pages\ViewEmployeeEvaluationForm::route('/{record}'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('employee_evaluation_form.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('employee_evaluation_form.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create employee evaluation form.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('employee_evaluation_form.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('employee_evaluation_form.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a employee evaluation form.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('employee_evaluation_form.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete employee evaluation forms.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}