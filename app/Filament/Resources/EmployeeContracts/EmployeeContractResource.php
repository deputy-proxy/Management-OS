<?php

namespace App\Filament\Resources\EmployeeContracts;

use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use App\Filament\Clusters\General\GeneralCluster;
use App\Filament\Clusters\HumanResources\HumanResourcesCluster;
use App\Filament\Resources\EmployeeContracts\Schemas\EmployeeContractForm;
use App\Filament\Resources\EmployeeContracts\Schemas\EmployeeContractInfolist;
use App\Filament\Resources\EmployeeContracts\Tables\EmployeeContractsTable;
use App\Models\EmployeeContract;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Hexters\HexaLite\HasHexaLite;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Carbon;
use UnitEnum;

class EmployeeContractResource extends Resource
{
    protected static ?int $navigationSort = 6;

    protected static ?string $cluster = GeneralCluster::class;

    protected static string|UnitEnum|null $navigationGroup = 'Human Resources';

    use HasHexaLite;

    protected static ?string $model = EmployeeContract::class;
    // protected static ?string $cluster = HumanResourcesCluster::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function defineGates(): array
    {
        return ['employee_contract.index' => __('Allows viewing the employee contract list'), 'employee_contract.create' => __('Allows creating a new employee contract'), 'employee_contract.update' => __('Allows updating employee contracts'), 'employee_contract.delete' => __('Allows deleting employee contracts')];
    }

    public static function getNavigationBadge(): ?string
    {
        if (! hexa()->can('employee_contract.index')) {
            return null;
        }

        $count = static::getModel()::query()
            ->whereBetween('end_date', [
                Carbon::today(),
                Carbon::today()->addMonths(3),
            ])
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $count = static::getModel()::query()
            ->whereBetween('end_date', [
                Carbon::today(),
                Carbon::today()->addMonths(3),
            ])
            ->count();

        // Red if expiring within 1 month, orange if within 3 months
        $expiringWithinMonth = static::getModel()::query()
            ->whereBetween('end_date', [
                Carbon::today(),
                Carbon::today()->addMonth(),
            ])
            ->count();

        if ($expiringWithinMonth > 0) {
            return 'danger';
        }

        return $count > 0 ? 'warning' : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Contracts expiring in the next 3 months';
    }

    public static function form(Schema $schema): Schema
    {
        return EmployeeContractForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EmployeeContractInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeeContractsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [ActivitiesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployeeContracts::route('/'),
            'create' => Pages\CreateEmployeeContract::route('/create'),
            'edit' => Pages\EditEmployeeContract::route('/{record}/edit'),
            'view' => Pages\ViewEmployeeContract::route('/{record}'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('employee_contract.index') ? Response::allow() : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('employee_contract.create') ? Response::allow() : Response::deny(__('You do not have permission to create employee contract.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('employee_contract.update') ? Response::allow() : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('employee_contract.delete') ? Response::allow() : Response::deny(__('You do not have permission to delete a employee contract.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('employee_contract.delete') ? Response::allow() : Response::deny(__('You do not have permission to delete employee contracts.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}