<?php

namespace App\Filament\Resources\EmployeeSalaries;

use App\Models\EmployeeSalary;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\EmployeeSalaries\Pages;
use App\Filament\Resources\EmployeeSalaries\Schemas\EmployeeSalaryForm;
use App\Filament\Resources\EmployeeSalaries\Schemas\EmployeeSalaryInfolist;
use App\Filament\Resources\EmployeeSalaries\Tables\EmployeeSalariesTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
use App\Filament\Clusters\HumanResources\HumanResourcesCluster;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use UnitEnum;
use App\Filament\Clusters\General\GeneralCluster;

class EmployeeSalaryResource extends Resource
{
    protected static ?string $cluster = GeneralCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Human Resources';
    use HasHexaLite;

    protected static ?string $model = EmployeeSalary::class;
    //protected static ?string $cluster = HumanResourcesCluster::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function defineGates(): array
    {
        return [
            'employee_salary.index' => __('Allows viewing the employee salary list'),
            'employee_salary.create' => __('Allows creating a new employee salary'),
            'employee_salary.update' => __('Allows updating employee salarys'),
            'employee_salary.delete' => __('Allows deleting employee salarys'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return EmployeeSalaryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EmployeeSalaryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeeSalariesTable::configure($table);
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
            'index' => Pages\ListEmployeeSalaries::route('/'),
            'create' => Pages\CreateEmployeeSalary::route('/create'),
            'edit' => Pages\EditEmployeeSalary::route('/{record}/edit'),
            'view' => Pages\ViewEmployeeSalary::route('/{record}'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('employee_salary.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('employee_salary.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create employee salary.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('employee_salary.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('employee_salary.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a employee salary.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('employee_salary.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete employee salarys.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
