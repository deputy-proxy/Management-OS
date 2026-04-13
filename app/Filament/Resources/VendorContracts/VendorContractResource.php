<?php

namespace App\Filament\Resources\VendorContracts;

use App\Models\VendorContract;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\VendorContracts\Pages;
use App\Filament\Resources\VendorContracts\Schemas\VendorContractForm;
use App\Filament\Resources\VendorContracts\Schemas\VendorContractInfolist;
use App\Filament\Resources\VendorContracts\Tables\VendorContractsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
class VendorContractResource extends Resource
{
    use HasHexaLite;

    protected static ?string $model = VendorContract::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function defineGates(): array
    {
        return [
            ActivitiesRelationManager::class,
            'vendor_contract.index' => __('Allows viewing the vendor contract list'),
            'vendor_contract.create' => __('Allows creating a new vendor contract'),
            'vendor_contract.update' => __('Allows updating vendor contracts'),
            'vendor_contract.delete' => __('Allows deleting vendor contracts'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return VendorContractForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return VendorContractInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VendorContractsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVendorContracts::route('/'),
            'create' => Pages\CreateVendorContract::route('/create'),
            'edit' => Pages\EditVendorContract::route('/{record}/edit'),
            'view' => Pages\ViewVendorContract::route('/{record}')
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('vendor_contract.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('vendor_contract.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create vendor contract.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('vendor_contract.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('vendor_contract.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a vendor contract.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('vendor_contract.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete vendor contracts.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
