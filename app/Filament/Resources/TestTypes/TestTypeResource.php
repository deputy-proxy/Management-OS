<?php

namespace App\Filament\Resources\TestTypes;

use App\Models\TestType;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\TestTypes\Pages;
use App\Filament\Resources\TestTypes\Schemas\TestTypeForm;
use App\Filament\Resources\TestTypes\Schemas\TestTypeInfolist;
use App\Filament\Resources\TestTypes\Tables\TestTypesTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use UnitEnum;
use App\Filament\Clusters\Academic\AcademicCluster;

class TestTypeResource extends Resource
{
    protected static ?int $navigationSort = 10;
    use HasHexaLite;
    protected static ?string $cluster = AcademicCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Tests Management';
    protected static ?string $model = TestType::class;

    protected static ?string $recordTitleAttribute = 'title';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return TestTypeForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TestTypeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TestTypesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [ActivitiesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTestTypes::route('/'),
            'create' => Pages\CreateTestType::route('/create'),
            'edit' => Pages\EditTestType::route('/{record}/edit'),
            'view' => Pages\ViewTestType::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public function defineGates(): array
    {
        return ['test_type.index' => __('Allows viewing the test type list'), 'test_type.create' => __('Allows creating a new test type'), 'test_type.update' => __('Allows updating test types'), 'test_type.delete' => __('Allows deleting test types')];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('test_type.index') ? Response::allow() : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('test_type.create') ? Response::allow() : Response::deny(__('You do not have permission to create test type.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('test_type.update') ? Response::allow() : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('test_type.delete') ? Response::allow() : Response::deny(__('You do not have permission to delete a test type.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('test_type.delete') ? Response::allow() : Response::deny(__('You do not have permission to delete test types.'));
    }
}

