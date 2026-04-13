<?php

namespace App\Filament\Resources\CustomerInvoices;

use App\Models\CustomerInvoice;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\CustomerInvoices\Pages;
use App\Filament\Resources\CustomerInvoices\Schemas\CustomerInvoiceForm;
use App\Filament\Resources\CustomerInvoices\Schemas\CustomerInvoiceInfolist;
use App\Filament\Resources\CustomerInvoices\Tables\CustomerInvoicesTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;

class CustomerInvoiceResource extends Resource
{
    use HasHexaLite;

    protected static ?string $model = CustomerInvoice::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function defineGates(): array
    {
        return [
            'customer_invoice.index' => __('Allows viewing the customer invoice list'),
            'customer_invoice.create' => __('Allows creating a new customer invoice'),
            'customer_invoice.update' => __('Allows updating customer invoices'),
            'customer_invoice.delete' => __('Allows deleting customer invoices'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return CustomerInvoiceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CustomerInvoiceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomerInvoicesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomerInvoices::route('/'),
            'create' => Pages\CreateCustomerInvoice::route('/create'),
            'edit' => Pages\EditCustomerInvoice::route('/{record}/edit'),
            'view' => Pages\ViewCustomerInvoice::route('/{record}'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('customer_invoice.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('customer_invoice.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create customer invoice.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('customer_invoice.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('customer_invoice.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a customer invoice.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('customer_invoice.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete customer invoices.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}

