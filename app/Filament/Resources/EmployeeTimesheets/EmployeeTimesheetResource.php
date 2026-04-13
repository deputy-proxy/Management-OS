<?php

namespace App\Filament\Resources\EmployeeTimesheets;

use App\Models\EmployeeTimesheet;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\EmployeeTimesheets\Pages;
use App\Filament\Resources\EmployeeTimesheets\Schemas\EmployeeTimesheetForm;
use App\Filament\Resources\EmployeeTimesheets\Schemas\EmployeeTimesheetInfolist;
use App\Filament\Resources\EmployeeTimesheets\Tables\EmployeeTimesheetsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
use App\Filament\Clusters\HumanResources\HumanResourcesCluster;
class EmployeeTimesheetResource extends Resource
{
    use HasHexaLite;

    protected static ?string $model = EmployeeTimesheet::class;

    //protected static ?string $cluster = HumanResourcesCluster::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function defineGates(): array
    {
        return [
            'employee_timesheet.index' => __('Allows viewing the employee timesheet list'),
            'employee_timesheet.create' => __('Allows creating a new employee timesheet'),
            'employee_timesheet.update' => __('Allows updating employee timesheets'),
            'employee_timesheet.delete' => __('Allows deleting employee timesheets'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return EmployeeTimesheetForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EmployeeTimesheetInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeeTimesheetsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployeeTimesheets::route('/'),
            'create' => Pages\CreateEmployeeTimesheet::route('/create'),
            'edit' => Pages\EditEmployeeTimesheet::route('/{record}/edit'),
            'view' => Pages\ViewEmployeeTimesheet::route('/{record}')
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('employee_timesheet.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('employee_timesheet.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create employee timesheet.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('employee_timesheet.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('employee_timesheet.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a employee timesheet.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('employee_timesheet.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete employee timesheets.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}

