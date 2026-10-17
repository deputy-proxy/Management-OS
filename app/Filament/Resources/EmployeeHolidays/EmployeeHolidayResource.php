<?php

namespace App\Filament\Resources\EmployeeHolidays;

use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use App\Filament\Clusters\General\GeneralCluster;
use App\Filament\Clusters\HumanResources\HumanResourcesCluster;
use App\Filament\Resources\EmployeeHolidays\Schemas\EmployeeHolidayForm;
use App\Filament\Resources\EmployeeHolidays\Schemas\EmployeeHolidayInfolist;
use App\Filament\Resources\EmployeeHolidays\Tables\EmployeeHolidaysTable;
use App\Models\EmployeeHoliday;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Hexters\HexaLite\HasHexaLite;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class EmployeeHolidayResource extends Resource
{
    protected static ?int $navigationSort = 10;

    protected static ?string $cluster = GeneralCluster::class;

    protected static string|UnitEnum|null $navigationGroup = 'Human Resources';

    use HasHexaLite;

    protected static ?string $model = EmployeeHoliday::class;

    protected static ?string $recordTitleAttribute = 'title';
    // protected static ?string $cluster = HumanResourcesCluster::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getNavigationBadge(): ?string
    {
        if (! hexa()->can('employee_holiday.index')) {
            return null;
        }

        $count = static::getModel()::query()
            ->where('status', 'Pending')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Pending holiday requests';
    }

    public function defineGates(): array
    {
        return [
            'employee_holiday.index' => __('Allows viewing the employee holiday list'),
            'employee_holiday.create' => __('Allows creating a new employee holiday'),
            'employee_holiday.update' => __('Allows updating employee holidays'),
            'employee_holiday.delete' => __('Allows deleting employee holidays'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return EmployeeHolidayForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EmployeeHolidayInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeeHolidaysTable::configure($table);
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
            'index' => Pages\ListEmployeeHolidays::route('/'),
            'create' => Pages\CreateEmployeeHoliday::route('/create'),
            'edit' => Pages\EditEmployeeHoliday::route('/{record}/edit'),
            'view' => Pages\ViewEmployeeHoliday::route('/{record}'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('employee_holiday.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('employee_holiday.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create employee holiday.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('employee_holiday.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('employee_holiday.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a employee holiday.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('employee_holiday.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete employee holidays.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}