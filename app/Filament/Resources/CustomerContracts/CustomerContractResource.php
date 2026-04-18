<?php

namespace App\Filament\Resources\CustomerContracts;

use App\Models\CustomerContract;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\CustomerContracts\Pages;
use App\Filament\Resources\CustomerContracts\Schemas\CustomerContractForm;
use App\Filament\Resources\CustomerContracts\Schemas\CustomerContractInfolist;
use App\Filament\Resources\CustomerContracts\Tables\CustomerContractsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use UnitEnum;
use App\Filament\Clusters\Finance\FinanceCluster;

class CustomerContractResource extends Resource
{
    protected static ?int $navigationSort = 2;
    protected static ?string $cluster = FinanceCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Customer Management';
    use HasHexaLite;

    protected static ?string $model = CustomerContract::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function defineGates(): array
    {
        return [
            'customer_contract.index' => __('Allows viewing the customer contract list'),
            'customer_contract.create' => __('Allows creating a new customer contract'),
            'customer_contract.update' => __('Allows updating customer contracts'),
            'customer_contract.delete' => __('Allows deleting customer contracts'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return CustomerContractForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CustomerContractInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomerContractsTable::configure($table);
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
            'index' => Pages\ListCustomerContracts::route('/'),
            'create' => Pages\CreateCustomerContract::route('/create'),
            'edit' => Pages\EditCustomerContract::route('/{record}/edit'),
            'view' => Pages\ViewCustomerContract::route('/{record}'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('customer_contract.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('customer_contract.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create customer contract.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('customer_contract.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('customer_contract.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a customer contract.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('customer_contract.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete customer contracts.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
