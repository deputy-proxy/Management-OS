<?php

namespace App\Filament\Resources\CustomerLeads;

use App\Models\CustomerLead;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\CustomerLeads\Pages;
use App\Filament\Resources\CustomerLeads\Schemas\CustomerLeadForm;
use App\Filament\Resources\CustomerLeads\Schemas\CustomerLeadInfolist;
use App\Filament\Resources\CustomerLeads\Tables\CustomerLeadsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use UnitEnum;
use App\Filament\Clusters\Finance\FinanceCluster;

class CustomerLeadResource extends Resource
{
    protected static ?int $navigationSort = 3;
    protected static ?string $cluster = FinanceCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Customer Management';
    use HasHexaLite;

    protected static ?string $model = CustomerLead::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function defineGates(): array
    {
        return [
            'customer_lead.index' => __('Allows viewing the customer lead list'),
            'customer_lead.create' => __('Allows creating a new customer lead'),
            'customer_lead.update' => __('Allows updating customer leads'),
            'customer_lead.delete' => __('Allows deleting customer leads'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return CustomerLeadForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CustomerLeadInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomerLeadsTable::configure($table);
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
            'index' => Pages\ListCustomerLeads::route('/'),
            'create' => Pages\CreateCustomerLead::route('/create'),
            'edit' => Pages\EditCustomerLead::route('/{record}/edit'),
            'view' => Pages\ViewCustomerLead::route('/{record}'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('customer_lead.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('customer_lead.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create customer lead.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('customer_lead.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('customer_lead.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a customer lead.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('customer_lead.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete customer leads.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
