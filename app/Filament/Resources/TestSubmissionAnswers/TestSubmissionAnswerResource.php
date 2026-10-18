<?php

namespace App\Filament\Resources\TestSubmissionAnswers;

use App\Filament\Clusters\Academic\AcademicCluster;
use App\Filament\Resources\TestSubmissionAnswers\Schemas\TestSubmissionAnswerForm;
use App\Filament\Resources\TestSubmissionAnswers\Schemas\TestSubmissionAnswerInfolist;
use App\Filament\Resources\TestSubmissionAnswers\Tables\TestSubmissionAnswersTable;
use App\Models\TestSubmissionAnswer;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Hexters\HexaLite\HasHexaLite;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class TestSubmissionAnswerResource extends Resource
{
    protected static ?int $navigationSort = 13;

    protected static ?string $cluster = AcademicCluster::class;

    protected static string|UnitEnum|null $navigationGroup = 'Tests Management';

    protected static ?string $model = TestSubmissionAnswer::class;

    protected static ?string $recordTitleAttribute = 'title';

    use HasHexaLite;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return TestSubmissionAnswerForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TestSubmissionAnswerInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TestSubmissionAnswersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTestSubmissionAnswers::route('/'),
            'create' => Pages\CreateTestSubmissionAnswer::route('/create'),
            'edit' => Pages\EditTestSubmissionAnswer::route('/{record}/edit'),
            'view' => Pages\ViewTestSubmissionAnswer::route('/{record}'),
        ];
    }

    public function defineGates(): array
    {
        return [
            'test_submission_answer.index' => __('Allows viewing the test submission answer list'),
            'test_submission_answer.create' => __('Allows creating a new test submission answer'),
            'test_submission_answer.update' => __('Allows updating test submission answers'),
            'test_submission_answer.delete' => __('Allows deleting test submission answers'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('test_submission_answer.index') ? Response::allow() : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('test_submission_answer.create') ? Response::allow() : Response::deny(__('You do not have permission to create test submission answer.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('test_submission_answer.update') ? Response::allow() : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('test_submission_answer.delete') ? Response::allow() : Response::deny(__('You do not have permission to delete a test submission answer.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('test_submission_answer.delete') ? Response::allow() : Response::deny(__('You do not have permission to delete test submission answers.'));
    }
}
