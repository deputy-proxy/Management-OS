<?php

namespace App\Filament\Resources\EmployeeMedicalLeaves;

use App\Models\EmployeeMedicalLeafe;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\EmployeeMedicalLeaves\Pages;
use App\Filament\Resources\EmployeeMedicalLeaves\Schemas\EmployeeMedicalLeafeForm;
use App\Filament\Resources\EmployeeMedicalLeaves\Schemas\EmployeeMedicalLeafeInfolist;
use App\Filament\Resources\EmployeeMedicalLeaves\Tables\EmployeeMedicalLeavesTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
use App\Filament\Clusters\HumanResources\HumanResourcesCluster;

class EmployeeMedicalLeafeResource extends Resource
{
    use HasHexaLite;

    protected static ?string $model = EmployeeMedicalLeafe::class;
    //protected static ?string $cluster = HumanResourcesCluster::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function defineGates(): array
    {
        return [
            'employee_medical_leafe.index' => __('Allows viewing the employee medical leafe list'),
            'employee_medical_leafe.create' => __('Allows creating a new employee medical leafe'),
            'employee_medical_leafe.update' => __('Allows updating employee medical leafes'),
            'employee_medical_leafe.delete' => __('Allows deleting employee medical leafes'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return EmployeeMedicalLeafeForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EmployeeMedicalLeafeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeeMedicalLeavesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployeeMedicalLeaves::route('/'),
            'create' => Pages\CreateEmployeeMedicalLeafe::route('/create'),
            'edit' => Pages\EditEmployeeMedicalLeafe::route('/{record}/edit'),
            'view' => Pages\ViewEmployeeMedicalLeafe::route('/{record}'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('employee_medical_leafe.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('employee_medical_leafe.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create employee medical leafe.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('employee_medical_leafe.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('employee_medical_leafe.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a employee medical leafe.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('employee_medical_leafe.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete employee medical leafes.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}

