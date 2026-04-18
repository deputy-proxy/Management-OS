<?php

namespace App\Filament\Resources\Groups;

use App\Models\Group;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\Groups\Pages;
use App\Filament\Resources\Groups\Schemas\GroupForm;
use App\Filament\Resources\Groups\Schemas\GroupInfolist;
use App\Filament\Resources\Groups\Tables\GroupsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use UnitEnum;
use App\Filament\Clusters\Academic\AcademicCluster;

class GroupResource extends Resource
{
    protected static ?int $navigationSort = 1;
    protected static ?string $cluster = AcademicCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Students Management';
    use HasHexaLite;

    protected static ?string $model = Group::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function defineGates(): array
    {
        return [
            'group.index' => __('Allows viewing the group list'),
            'group.create' => __('Allows creating a new group'),
            'group.update' => __('Allows updating groups'),
            'group.delete' => __('Allows deleting groups'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return GroupForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return GroupInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GroupsTable::configure($table);
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
            'index' => Pages\ListGroups::route('/'),
            'create' => Pages\CreateGroup::route('/create'),
            'edit' => Pages\EditGroup::route('/{record}/edit'),
            'view' => Pages\ViewGroup::route('/{record}'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('group.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('group.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create group.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('group.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('group.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a group.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('group.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete groups.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
