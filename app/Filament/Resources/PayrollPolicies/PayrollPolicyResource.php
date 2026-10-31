<?php

namespace App\Filament\Resources\PayrollPolicies;

use App\Filament\Clusters\General\GeneralCluster;
use App\Filament\Resources\PayrollPolicies\Pages\CreatePayrollPolicy;
use App\Filament\Resources\PayrollPolicies\Pages\EditPayrollPolicy;
use App\Filament\Resources\PayrollPolicies\Pages\ListPayrollPolicies;
use App\Filament\Resources\PayrollPolicies\Pages\ViewPayrollPolicy;
use App\Filament\Resources\PayrollPolicies\Schemas\PayrollPolicyForm;
use App\Filament\Resources\PayrollPolicies\Schemas\PayrollPolicyInfolist;
use App\Filament\Resources\PayrollPolicies\Tables\PayrollPoliciesTable;
use App\Models\PayrollPolicy;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Hexters\HexaLite\HasHexaLite;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PayrollPolicyResource extends Resource
{
    use HasHexaLite;

    protected static ?string $model = PayrollPolicy::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 8;

    protected static ?string $cluster = GeneralCluster::class;

    protected static string|UnitEnum|null $navigationGroup = 'Human Resources';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    public function defineGates(): array
    {
        return [
            'payroll_policy.index' => __('Allows viewing payroll policy versions'),
            'payroll_policy.create' => __('Allows creating payroll policy versions'),
            'payroll_policy.update' => __('Allows editing draft payroll policies'),
            'payroll_policy.activate' => __('Allows activating payroll policies'),
            'payroll_policy.retire' => __('Allows retiring payroll policies'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return hexa()->can('payroll_policy.index')
            ? (string) PayrollPolicy::query()->where('status', 'draft')->count()
            : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return PayrollPolicyForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PayrollPolicyInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PayrollPoliciesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayrollPolicies::route('/'),
            'create' => CreatePayrollPolicy::route('/create'),
            'edit' => EditPayrollPolicy::route('/{record}/edit'),
            'view' => ViewPayrollPolicy::route('/{record}'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('payroll_policy.index') ? Response::allow() : Response::deny();
    }

    public static function getViewAuthorizationResponse(Model $record): Response
    {
        return $record instanceof PayrollPolicy && hexa()->can('payroll_policy.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('payroll_policy.create') ? Response::allow() : Response::deny();
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return $record instanceof PayrollPolicy
            && $record->status === 'draft'
            && hexa()->can('payroll_policy.update')
                ? Response::allow()
                : Response::deny();
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return Response::deny('Payroll policy versions are retained as audit records.');
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return Response::deny('Payroll policy versions are retained as audit records.');
    }
}
