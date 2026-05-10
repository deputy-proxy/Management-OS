<?php

namespace App\Filament\Resources\CustomerInteractions;

use App\Models\CustomerInteraction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\CustomerInteractions\Pages;
use App\Filament\Resources\CustomerInteractions\Schemas\CustomerInteractionForm;
use App\Filament\Resources\CustomerInteractions\Schemas\CustomerInteractionInfolist;
use App\Filament\Resources\CustomerInteractions\Tables\CustomerInteractionsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use UnitEnum;
use App\Filament\Clusters\Finance\FinanceCluster;
class CustomerInteractionResource extends Resource
{
    protected static ?int $navigationSort = 4;
    protected static ?string $cluster = FinanceCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Customer Management';
    use HasHexaLite;

    protected static ?string $model = CustomerInteraction::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function defineGates(): array
    {
        return [
            'customer_interaction.index' => __('Allows viewing the customer interaction list'),
            'customer_interaction.create' => __('Allows creating a new customer interaction'),
            'customer_interaction.update' => __('Allows updating customer interactions'),
            'customer_interaction.delete' => __('Allows deleting customer interactions'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return CustomerInteractionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CustomerInteractionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomerInteractionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomerInteractions::route('/'),
            'create' => Pages\CreateCustomerInteraction::route('/create'),
            'edit' => Pages\EditCustomerInteraction::route('/{record}/edit'),
            'view' => Pages\ViewCustomerInteraction::route('/{record}')
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('customer_interaction.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('customer_interaction.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create customer interaction.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('customer_interaction.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('customer_interaction.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a customer interaction.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('customer_interaction.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete customer interactions.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
