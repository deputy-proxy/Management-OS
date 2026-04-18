<?php

namespace App\Filament\Resources\TestSubmissions;

use App\Models\TestSubmission;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\TestSubmissions\Pages;
use App\Filament\Resources\TestSubmissions\Schemas\TestSubmissionForm;
use App\Filament\Resources\TestSubmissions\Schemas\TestSubmissionInfolist;
use App\Filament\Resources\TestSubmissions\Tables\TestSubmissionsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use UnitEnum;
use App\Filament\Clusters\Academic\AcademicCluster;

class TestSubmissionResource extends Resource
{
    protected static ?int $navigationSort = 3;
    protected static ?string $cluster = AcademicCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Tests Management';
    use HasHexaLite;

    protected static ?string $model = TestSubmission::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function defineGates(): array
    {
        return [
            'test_submission.index' => __('Allows viewing the test submission list'),
            'test_submission.create' => __('Allows creating a new test submission'),
            'test_submission.update' => __('Allows updating test submissions'),
            'test_submission.delete' => __('Allows deleting test submissions'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return TestSubmissionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TestSubmissionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TestSubmissionsTable::configure($table);
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
            'index' => Pages\ListTestSubmissions::route('/'),
            'create' => Pages\CreateTestSubmission::route('/create'),
            'edit' => Pages\EditTestSubmission::route('/{record}/edit'),
            'view' => Pages\ViewTestSubmission::route('/{record}'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('test_submission.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('test_submission.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create test submission.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('test_submission.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('test_submission.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a test submission.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('test_submission.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete test submissions.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
