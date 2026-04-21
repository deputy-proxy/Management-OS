<?php

namespace App\Filament\Resources\Tests;

use App\Models\Test;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\Tests\Pages;
use App\Filament\Resources\Tests\Schemas\TestForm;
use App\Filament\Resources\Tests\Schemas\TestInfolist;
use App\Filament\Resources\Tests\Tables\TestsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use App\Filament\Resources\TestResource\RelationManagers\QuestionsRelationManager;
use UnitEnum;
use App\Filament\Clusters\Academic\AcademicCluster;
class TestResource extends Resource
{
    protected static ?int $navigationSort = 0;
    protected static ?string $cluster = AcademicCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Tests Management';
    use HasHexaLite;

    protected static ?string $model = Test::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';
    public function defineGates(): array
    {
        return ['test.index' => __('Allows viewing the test list'), 'test.create' => __('Allows creating a new test'), 'test.update' => __('Allows updating tests'), 'test.delete' => __('Allows deleting tests')];
    }

    public static function form(Schema $schema): Schema
    {
        return TestForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TestsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ActivitiesRelationManager::class,
            QuestionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTests::route('/'),
            'create' => Pages\CreateTest::route('/create'),
            'edit' => Pages\EditTest::route('/{record}/edit'),
            'view' => Pages\ViewTest::route('/{record}')
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('test.index') ? Response::allow() : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('test.create') ? Response::allow() : Response::deny(__('You do not have permission to create test.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('test.update') ? Response::allow() : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('test.delete') ? Response::allow() : Response::deny(__('You do not have permission to delete a test.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('test.delete') ? Response::allow() : Response::deny(__('You do not have permission to delete tests.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
