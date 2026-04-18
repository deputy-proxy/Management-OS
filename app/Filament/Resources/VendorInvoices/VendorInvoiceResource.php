<?php

namespace App\Filament\Resources\VendorInvoices;

use App\Models\VendorInvoice;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\VendorInvoices\Pages;
use App\Filament\Resources\VendorInvoices\Schemas\VendorInvoiceForm;
use App\Filament\Resources\VendorInvoices\Schemas\VendorInvoiceInfolist;
use App\Filament\Resources\VendorInvoices\Tables\VendorInvoicesTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use UnitEnum;
use App\Filament\Clusters\Finance\FinanceCluster;
class VendorInvoiceResource extends Resource
{
    protected static ?int $navigationSort = 2;
    protected static ?string $cluster = FinanceCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Vendor Management';
    use HasHexaLite;

    protected static ?string $model = VendorInvoice::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function defineGates(): array
    {
        return [
            ActivitiesRelationManager::class,
            'vendor_invoice.index' => __('Allows viewing the vendor invoice list'),
            'vendor_invoice.create' => __('Allows creating a new vendor invoice'),
            'vendor_invoice.update' => __('Allows updating vendor invoices'),
            'vendor_invoice.delete' => __('Allows deleting vendor invoices'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return VendorInvoiceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return VendorInvoiceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VendorInvoicesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ActivitiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVendorInvoices::route('/'),
            'create' => Pages\CreateVendorInvoice::route('/create'),
            'edit' => Pages\EditVendorInvoice::route('/{record}/edit'),
            'view' => Pages\ViewVendorInvoice::route('/{record}')
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('vendor_invoice.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('vendor_invoice.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create vendor invoice.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('vendor_invoice.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('vendor_invoice.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a vendor invoice.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('vendor_invoice.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete vendor invoices.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
