<?php

namespace App\Filament\Resources\TestSimulations;

use App\Filament\Clusters\Academic\AcademicCluster;
use App\Filament\Resources\TestSimulations\Schemas\TestSimulationForm;
use App\Filament\Resources\TestSimulations\Schemas\TestSimulationInfolist;
use App\Filament\Resources\TestSimulations\Tables\TestSimulationsTable;
use App\Models\TestSimulation;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Hexters\HexaLite\HasHexaLite;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class TestSimulationResource extends Resource
{
    use HasHexaLite;

    protected static ?string $model = TestSimulation::class;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 10;

    protected static ?string $cluster = AcademicCluster::class;

    protected static string|UnitEnum|null $navigationGroup = 'Tests Management';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    public static function form(Schema $schema): Schema
    {
        return TestSimulationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TestSimulationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TestSimulationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTestSimulations::route('/'),
            'create' => Pages\CreateTestSimulation::route('/create'),
            'edit' => Pages\EditTestSimulation::route('/{record}/edit'),
            'view' => Pages\ViewTestSimulation::route('/{record}'),
        ];
    }

    public function defineGates(): array
    {
        return [
            'test_simulation.index' => __('Allows viewing test simulations'),
            'test_simulation.create' => __('Allows creating test simulations'),
            'test_simulation.update' => __('Allows updating test simulations'),
            'test_simulation.delete' => __('Allows deleting test simulations'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('test_simulation.index') ? Response::allow() : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('test_simulation.create') ? Response::allow() : Response::deny();
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('test_simulation.update') ? Response::allow() : Response::deny();
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('test_simulation.delete') ? Response::allow() : Response::deny();
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('test_simulation.delete') ? Response::allow() : Response::deny();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
