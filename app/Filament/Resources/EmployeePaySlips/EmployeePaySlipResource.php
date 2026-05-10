<?php

namespace App\Filament\Resources\EmployeePaySlips;

use App\Models\EmployeePaySlip;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\EmployeePaySlips\Pages;
use App\Filament\Resources\EmployeePaySlips\Schemas\EmployeePaySlipForm;
use App\Filament\Resources\EmployeePaySlips\Schemas\EmployeePaySlipInfolist;
use App\Filament\Resources\EmployeePaySlips\Tables\EmployeePaySlipsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;
use App\Filament\Clusters\General\GeneralCluster;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
class EmployeePaySlipResource extends Resource
{
    protected static ?int $navigationSort = 7;

    protected static ?string $cluster = GeneralCluster::class;

    protected static string|UnitEnum|null $navigationGroup = 'Human Resources';
    use HasHexaLite;

    protected static ?string $model = EmployeePaySlip::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';
    
    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()
            ->where('status', 'Open')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Number of open pay slips';
    }

    public static function form(Schema $schema): Schema
    {
        return EmployeePaySlipForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EmployeePaySlipInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeePaySlipsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployeePaySlips::route('/'),
            'create' => Pages\CreateEmployeePaySlip::route('/create'),
            'edit' => Pages\EditEmployeePaySlip::route('/{record}/edit'),
            'view' => Pages\ViewEmployeePaySlip::route('/{record}')
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public function defineGates(): array
    {
        return ['employee_pay_slip.index' => __('Allows viewing the employee pay slip list'), 'employee_pay_slip.create' => __('Allows creating a new employee pay slip'), 'employee_pay_slip.update' => __('Allows updating employee pay slips'), 'employee_pay_slip.delete' => __('Allows deleting employee pay slips')];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('employee_pay_slip.index') ? Response::allow() : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('employee_pay_slip.create') ? Response::allow() : Response::deny(__('You do not have permission to create employee pay slip.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('employee_pay_slip.update') ? Response::allow() : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('employee_pay_slip.delete') ? Response::allow() : Response::deny(__('You do not have permission to delete an employee pay slip.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('employee_pay_slip.delete') ? Response::allow() : Response::deny(__('You do not have permission to delete employee pay slips.'));
    }
}

