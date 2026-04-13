<?php

namespace App\Filament\Resources\TestResults;

use App\Models\TestResult;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\TestResults\Pages;
use App\Filament\Resources\TestResults\Schemas\TestResultForm;
use App\Filament\Resources\TestResults\Schemas\TestResultInfolist;
use App\Filament\Resources\TestResults\Tables\TestResultsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;

class TestResultResource extends Resource
{
    use HasHexaLite;

    protected static ?string $model = TestResult::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function defineGates(): array
    {
        return [
            'test_result.index' => __('Allows viewing the test result list'),
            'test_result.create' => __('Allows creating a new test result'),
            'test_result.update' => __('Allows updating test results'),
            'test_result.delete' => __('Allows deleting test results'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return TestResultForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TestResultInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TestResultsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTestResults::route('/'),
            'create' => Pages\CreateTestResult::route('/create'),
            'edit' => Pages\EditTestResult::route('/{record}/edit'),
            'view' => Pages\ViewTestResult::route('/{record}'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('test_result.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('test_result.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create test result.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('test_result.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('test_result.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a test result.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('test_result.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete test results.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}

