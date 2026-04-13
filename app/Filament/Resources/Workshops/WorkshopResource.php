<?php

namespace App\Filament\Resources\Workshops;

use App\Models\Workshop;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\Workshops\Pages;
use App\Filament\Resources\Workshops\Schemas\WorkshopForm;
use App\Filament\Resources\Workshops\Schemas\WorkshopInfolist;
use App\Filament\Resources\Workshops\Tables\WorkshopsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;

class WorkshopResource extends Resource
{
    use HasHexaLite;

    protected static ?string $model = Workshop::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function defineGates(): array
    {
        return [
            'workshop.index' => __('Allows viewing the workshop list'),
            'workshop.create' => __('Allows creating a new workshop'),
            'workshop.update' => __('Allows updating workshops'),
            'workshop.delete' => __('Allows deleting workshops'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return WorkshopForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WorkshopInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkshopsTable::configure($table);
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
            'index' => Pages\ListWorkshops::route('/'),
            'create' => Pages\CreateWorkshop::route('/create'),
            'edit' => Pages\EditWorkshop::route('/{record}/edit'),
            'view' => Pages\ViewWorkshop::route('/{record}'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('workshop.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('workshop.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create workshop.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('workshop.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('workshop.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a workshop.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('workshop.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete workshops.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
