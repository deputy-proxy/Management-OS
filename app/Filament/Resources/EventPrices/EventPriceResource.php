<?php

namespace App\Filament\Resources\EventPrices;

use App\Models\EventPrice;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\EventPrices\Pages;
use App\Filament\Resources\EventPrices\Schemas\EventPriceForm;
use App\Filament\Resources\EventPrices\Schemas\EventPriceInfolist;
use App\Filament\Resources\EventPrices\Tables\EventPricesTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;

class EventPriceResource extends Resource
{
    use HasHexaLite;

    protected static ?string $model = EventPrice::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function defineGates(): array
    {
        return [
            'event_price.index' => __('Allows viewing the event price list'),
            'event_price.create' => __('Allows creating a new event price'),
            'event_price.update' => __('Allows updating event prices'),
            'event_price.delete' => __('Allows deleting event prices'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return EventPriceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EventPriceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EventPricesTable::configure($table);
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
            'index' => Pages\ListEventPrices::route('/'),
            'create' => Pages\CreateEventPrice::route('/create'),
            'edit' => Pages\EditEventPrice::route('/{record}/edit'),
            'view' => Pages\ViewEventPrice::route('/{record}'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('event_price.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('event_price.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create event price.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('event_price.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('event_price.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a event price.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('event_price.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete event prices.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}

