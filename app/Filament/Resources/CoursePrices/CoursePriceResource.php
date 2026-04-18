<?php

namespace App\Filament\Resources\CoursePrices;

use App\Models\CoursePrice;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\CoursePrices\Pages;
use App\Filament\Resources\CoursePrices\Schemas\CoursePriceForm;
use App\Filament\Resources\CoursePrices\Schemas\CoursePriceInfolist;
use App\Filament\Resources\CoursePrices\Tables\CoursePricesTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use UnitEnum;
use App\Filament\Clusters\Products\ProductsCluster;

class CoursePriceResource extends Resource
{
    protected static ?int $navigationSort = 2;
    protected static ?string $cluster = ProductsCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Course Management';
    use HasHexaLite;

    protected static ?string $model = CoursePrice::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function defineGates(): array
    {
        return [
            'course_price.index' => __('Allows viewing the course price list'),
            'course_price.create' => __('Allows creating a new course price'),
            'course_price.update' => __('Allows updating course prices'),
            'course_price.delete' => __('Allows deleting course prices'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return CoursePriceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CoursePriceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CoursePricesTable::configure($table);
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
            'index' => Pages\ListCoursePrices::route('/'),
            'create' => Pages\CreateCoursePrice::route('/create'),
            'edit' => Pages\EditCoursePrice::route('/{record}/edit'),
            'view' => Pages\ViewCoursePrice::route('/{record}'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('course_price.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('course_price.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create course price.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('course_price.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('course_price.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a course price.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('course_price.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete course prices.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
