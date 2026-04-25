<?php

namespace App\Filament\Resources\Taxes;

use App\Models\Tax;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\Taxes\Pages;
use App\Filament\Resources\Taxes\Schemas\TaxForm;
use App\Filament\Resources\Taxes\Schemas\TaxInfolist;
use App\Filament\Resources\Taxes\Tables\TaxesTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use UnitEnum;
use App\Filament\Clusters\Finance\FinanceCluster;

class TaxResource extends Resource
{
    protected static ?string $cluster = FinanceCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Treasurery & Tax';
    use HasHexaLite;

    protected static ?string $model = Tax::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function defineGates(): array
    {
        return [
            'tax.index' => __('Allows viewing the tax list'),
            'tax.create' => __('Allows creating a new tax'),
            'tax.update' => __('Allows updating taxs'),
            'tax.delete' => __('Allows deleting taxs'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return TaxForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TaxInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TaxesTable::configure($table);
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
            'index' => Pages\ListTaxes::route('/'),
            'create' => Pages\CreateTax::route('/create'),
            'edit' => Pages\EditTax::route('/{record}/edit'),
            'view' => Pages\ViewTax::route('/{record}'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('tax.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('tax.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create tax.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('tax.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('tax.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a tax.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('tax.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete taxs.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
