<?php

namespace App\Filament\Resources\PayrollPeriodInputs;

use App\Filament\Clusters\General\GeneralCluster;
use App\Filament\Resources\PayrollPeriodInputs\Schemas\PayrollPeriodInputForm;
use App\Filament\Resources\PayrollPeriodInputs\Tables\PayrollPeriodInputsTable;
use App\Models\PayrollPeriodInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Hexters\HexaLite\HasHexaLite;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PayrollPeriodInputResource extends Resource
{
    use HasHexaLite;

    protected static ?string $model = PayrollPeriodInput::class;

    protected static ?string $recordTitleAttribute = 'description';

    protected static ?int $navigationSort = 9;

    protected static ?string $cluster = GeneralCluster::class;

    protected static string|UnitEnum|null $navigationGroup = 'Human Resources';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    public function defineGates(): array
    {
        return [
            'payroll_period_input.index' => __('View payroll period inputs'),
            'payroll_period_input.create' => __('Create payroll period inputs'),
            'payroll_period_input.update' => __('Edit draft payroll period inputs'),
            'payroll_period_input.submit' => __('Submit payroll period inputs for review'),
            'payroll_period_input.approve' => __('Approve or reject payroll period inputs'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return PayrollPeriodInputForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PayrollPeriodInputsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayrollPeriodInputs::route('/'),
            'create' => Pages\CreatePayrollPeriodInput::route('/create'),
            'edit' => Pages\EditPayrollPeriodInput::route('/{record}/edit'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('payroll_period_input.index') ? Response::allow() : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('payroll_period_input.create') ? Response::allow() : Response::deny();
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return $record instanceof PayrollPeriodInput && in_array($record->status, ['draft', 'rejected'], true) && hexa()->can('payroll_period_input.update') ? Response::allow() : Response::deny('Submitted or approved payroll inputs cannot be edited.');
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return Response::deny('Payroll period inputs are retained as audit records.');
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return Response::deny('Payroll period inputs are retained as audit records.');
    }
}
