<?php

namespace App\Filament\Resources\FinancialAccounts;

use App\Models\FinancialAccount;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\FinancialAccounts\Pages;
use App\Filament\Resources\FinancialAccounts\Schemas\FinancialAccountForm;
use App\Filament\Resources\FinancialAccounts\Schemas\FinancialAccountInfolist;
use App\Filament\Resources\FinancialAccounts\Tables\FinancialAccountsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use UnitEnum;
use App\Filament\Clusters\Finance\FinanceCluster;
class FinancialAccountResource extends Resource
{
    protected static ?int $navigationSort = 8;
    protected static ?string $cluster = FinanceCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Treasurery & Tax';
    use HasHexaLite;

    protected static ?string $model = FinancialAccount::class;

    protected static ?string $recordTitleAttribute = 'title';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function defineGates(): array
    {
        return [
            'financial_account.index' => __('Allows viewing the financial account list'),
            'financial_account.create' => __('Allows creating a new financial account'),
            'financial_account.update' => __('Allows updating financial accounts'),
            'financial_account.delete' => __('Allows deleting financial accounts'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return FinancialAccountForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FinancialAccountInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FinancialAccountsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFinancialAccounts::route('/'),
            'create' => Pages\CreateFinancialAccount::route('/create'),
            'edit' => Pages\EditFinancialAccount::route('/{record}/edit'),
            'view' => Pages\ViewFinancialAccount::route('/{record}')
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('financial_account.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('financial_account.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create financial account.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('financial_account.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('financial_account.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a financial account.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('financial_account.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete financial accounts.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
