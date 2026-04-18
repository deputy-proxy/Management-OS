<?php

namespace App\Filament\Resources\CourseMaterials;

use App\Models\CourseMaterial;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\CourseMaterials\Pages;
use App\Filament\Resources\CourseMaterials\Schemas\CourseMaterialForm;
use App\Filament\Resources\CourseMaterials\Schemas\CourseMaterialInfolist;
use App\Filament\Resources\CourseMaterials\Tables\CourseMaterialsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use UnitEnum;
use App\Filament\Clusters\Products\ProductsCluster;
class CourseMaterialResource extends Resource
{
    protected static ?int $navigationSort = 1;
    protected static ?string $cluster = ProductsCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Course Management';
    use HasHexaLite;

    protected static ?string $model = CourseMaterial::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';
    public function defineGates(): array
    {
        return ['course_material.index' => __('Allows viewing the course material list'), 'course_material.create' => __('Allows creating a new course material'), 'course_material.update' => __('Allows updating course materials'), 'course_material.delete' => __('Allows deleting course materials')];
    }

    public static function form(Schema $schema): Schema
    {
        return CourseMaterialForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CourseMaterialInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CourseMaterialsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [ActivitiesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCourseMaterials::route('/'),
            'create' => Pages\CreateCourseMaterial::route('/create'),
            'edit' => Pages\EditCourseMaterial::route('/{record}/edit'),
            'view' => Pages\ViewCourseMaterial::route('/{record}')
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('course_material.index') ? Response::allow() : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('course_material.create') ? Response::allow() : Response::deny(__('You do not have permission to create course material.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('course_material.update') ? Response::allow() : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('course_material.delete') ? Response::allow() : Response::deny(__('You do not have permission to delete a course material.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('course_material.delete') ? Response::allow() : Response::deny(__('You do not have permission to delete course materials.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
