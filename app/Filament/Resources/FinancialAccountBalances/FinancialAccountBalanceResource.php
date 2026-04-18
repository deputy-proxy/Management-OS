<?php

namespace App\Filament\Resources\FinancialAccountBalances;

use App\Models\FinancialAccountBalance;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\FinancialAccountBalances\Pages;
use App\Filament\Resources\FinancialAccountBalances\Schemas\FinancialAccountBalanceForm;
use App\Filament\Resources\FinancialAccountBalances\Schemas\FinancialAccountBalanceInfolist;
use App\Filament\Resources\FinancialAccountBalances\Tables\FinancialAccountBalancesTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use UnitEnum;
use App\Filament\Clusters\Finance\FinanceCluster;

class FinancialAccountBalanceResource extends Resource
{
    protected static ?int $navigationSort = 0;
    protected static ?string $cluster = FinanceCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Treasurery & Tax';
    use HasHexaLite;

    protected static ?string $model = FinancialAccountBalance::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function defineGates(): array
    {
        return [
            'financial_account_balance.index' => __('Allows viewing the financial account balance list'),
            'financial_account_balance.create' => __('Allows creating a new financial account balance'),
            'financial_account_balance.update' => __('Allows updating financial account balances'),
            'financial_account_balance.delete' => __('Allows deleting financial account balances'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return FinancialAccountBalanceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FinancialAccountBalanceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FinancialAccountBalancesTable::configure($table);
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
            'index' => Pages\ListFinancialAccountBalances::route('/'),
            'create' => Pages\CreateFinancialAccountBalance::route('/create'),
            'edit' => Pages\EditFinancialAccountBalance::route('/{record}/edit'),
            'view' => Pages\ViewFinancialAccountBalance::route('/{record}'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('financial_account_balance.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('financial_account_balance.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create financial account balance.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('financial_account_balance.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('financial_account_balance.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a financial account balance.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('financial_account_balance.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete financial account balances.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
