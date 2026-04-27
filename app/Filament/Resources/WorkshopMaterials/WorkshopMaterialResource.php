<?php

namespace App\Filament\Resources\WorkshopMaterials;

use App\Models\WorkshopMaterial;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\WorkshopMaterials\Pages;
use App\Filament\Resources\WorkshopMaterials\Schemas\WorkshopMaterialForm;
use App\Filament\Resources\WorkshopMaterials\Schemas\WorkshopMaterialInfolist;
use App\Filament\Resources\WorkshopMaterials\Tables\WorkshopMaterialsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use UnitEnum;
use App\Filament\Clusters\Portfolio\PortfolioCluster;
class WorkshopMaterialResource extends Resource
{
    protected static ?int $navigationSort = 13;
    protected static ?string $cluster = PortfolioCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Workshop Management';
    use HasHexaLite;

    protected static ?string $model = WorkshopMaterial::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';
    public function defineGates(): array
    {
        return ['workshop_material.index' => __('Allows viewing the workshop material list'), 'workshop_material.create' => __('Allows creating a new workshop material'), 'workshop_material.update' => __('Allows updating workshop materials'), 'workshop_material.delete' => __('Allows deleting workshop materials')];
    }

    public static function form(Schema $schema): Schema
    {
        return WorkshopMaterialForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WorkshopMaterialInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkshopMaterialsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [ActivitiesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWorkshopMaterials::route('/'),
            'create' => Pages\CreateWorkshopMaterial::route('/create'),
            'edit' => Pages\EditWorkshopMaterial::route('/{record}/edit'),
            'view' => Pages\ViewWorkshopMaterial::route('/{record}')
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('workshop_material.index') ? Response::allow() : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('workshop_material.create') ? Response::allow() : Response::deny(__('You do not have permission to create workshop material.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('workshop_material.update') ? Response::allow() : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('workshop_material.delete') ? Response::allow() : Response::deny(__('You do not have permission to delete a workshop material.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('workshop_material.delete') ? Response::allow() : Response::deny(__('You do not have permission to delete workshop materials.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
