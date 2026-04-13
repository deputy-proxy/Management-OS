<?php

namespace App\Filament\Resources\TestQuestions;

use App\Models\TestQuestion;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\TestQuestions\Pages;
use App\Filament\Resources\TestQuestions\Schemas\TestQuestionForm;
use App\Filament\Resources\TestQuestions\Schemas\TestQuestionInfolist;
use App\Filament\Resources\TestQuestions\Tables\TestQuestionsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;

class TestQuestionResource extends Resource
{
    use HasHexaLite;

    protected static ?string $model = TestQuestion::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function defineGates(): array
    {
        return [
            'test_question.index' => __('Allows viewing the test question list'),
            'test_question.create' => __('Allows creating a new test question'),
            'test_question.update' => __('Allows updating test questions'),
            'test_question.delete' => __('Allows deleting test questions'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return TestQuestionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TestQuestionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TestQuestionsTable::configure($table);
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
            'index' => Pages\ListTestQuestions::route('/'),
            'create' => Pages\CreateTestQuestion::route('/create'),
            'edit' => Pages\EditTestQuestion::route('/{record}/edit'),
            'view' => Pages\ViewTestQuestion::route('/{record}'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('test_question.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('test_question.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create test question.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('test_question.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('test_question.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a test question.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('test_question.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete test questions.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
