<?php

namespace App\Filament\Resources\Courses;

use App\Models\Course;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\Courses\Pages;
use App\Filament\Resources\Courses\Schemas\CourseForm;
use App\Filament\Resources\Courses\Schemas\CourseInfolist;
use App\Filament\Resources\Courses\Tables\CoursesTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Hexters\HexaLite\HasHexaLite;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use UnitEnum;
use App\Filament\Clusters\Portfolio\PortfolioCluster;


class CourseResource extends Resource
{
    protected static ?int $navigationSort = 2;
    protected static ?string $cluster = PortfolioCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Course Management';
    protected static ?string $model = Course::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $recordTitleAttribute = 'title';
    
    use HasHexaLite;

    public function defineGates(): array
    {
        return [
            'course.index' => __('Allows viewing the course list'),
            'course.create' => __('Allows creating a new course'),
            'course.update' => __('Allows updating courses'),
            'course.delete' => __('Allows deleting courses'),
        ];
    }
    public static function form(Schema $schema): Schema
    {
        return CourseForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CourseInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CoursesTable::configure($table);
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
            'index' => Pages\ListCourses::route('/'),
            'create' => Pages\CreateCourse::route('/create'),
            'edit' => Pages\EditCourse::route('/{record}/edit'),
            'view' => Pages\ViewCourse::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }


// Authorization using HexaLite

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('course.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('course.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create course.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('course.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('course.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a course.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('course.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete courses.'));
    }
}
