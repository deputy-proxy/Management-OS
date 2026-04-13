<?php

namespace App\Filament\Resources\WorkshopPrices;

use App\Models\WorkshopPrice;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\WorkshopPrices\Pages;
use App\Filament\Resources\WorkshopPrices\Schemas\WorkshopPriceForm;
use App\Filament\Resources\WorkshopPrices\Schemas\WorkshopPriceInfolist;
use App\Filament\Resources\WorkshopPrices\Tables\WorkshopPricesTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;

class WorkshopPriceResource extends Resource
{
    use HasHexaLite;

    protected static ?string $model = WorkshopPrice::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function defineGates(): array
    {
        return [
            'workshop_price.index' => __('Allows viewing the workshop price list'),
            'workshop_price.create' => __('Allows creating a new workshop price'),
            'workshop_price.update' => __('Allows updating workshop prices'),
            'workshop_price.delete' => __('Allows deleting workshop prices'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return WorkshopPriceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WorkshopPriceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkshopPricesTable::configure($table);
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
            'index' => Pages\ListWorkshopPrices::route('/'),
            'create' => Pages\CreateWorkshopPrice::route('/create'),
            'edit' => Pages\EditWorkshopPrice::route('/{record}/edit'),
            'view' => Pages\ViewWorkshopPrice::route('/{record}'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('workshop_price.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('workshop_price.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create workshop price.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('workshop_price.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('workshop_price.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a workshop price.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('workshop_price.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete workshop prices.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}

