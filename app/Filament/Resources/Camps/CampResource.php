<?php

namespace App\Filament\Resources\Camps;

use App\Models\Camp;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\Camps\Pages;
use App\Filament\Resources\Camps\Schemas\CampForm;
use App\Filament\Resources\Camps\Schemas\CampInfolist;
use App\Filament\Resources\Camps\Tables\CampsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;
use Hexters\HexaLite\HasHexaLite;
use App\Filament\Clusters\Portfolio\PortfolioCluster;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

class CampResource extends Resource
{
    protected static ?int $navigationSort = 1;
    protected static ?string $cluster = PortfolioCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Camp Management';
    protected static ?string $model = Camp::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';
    use HasHexaLite;

    public static function form(Schema $schema): Schema
    {
        return CampForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CampInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CampsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCamps::route('/'),
            'create' => Pages\CreateCamp::route('/create'),
            'edit' => Pages\EditCamp::route('/{record}/edit'),
            'view' => Pages\ViewCamp::route('/{record}')
        ];
    }

    public function defineGates(): array
    {
        return [
            'camp.index' => __('Allows viewing the camp list'),
            'camp.create' => __('Allows creating a new camp'),
            'camp.update' => __('Allows updating camps'),
            'camp.delete' => __('Allows deleting camps'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('camp.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('camp.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create camp.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('camp.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('camp.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a camp.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('camp.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete camps.'));
    }

}

