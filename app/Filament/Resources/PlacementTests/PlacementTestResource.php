<?php

namespace App\Filament\Resources\PlacementTests;

use App\Models\PlacementTest;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\PlacementTests\Pages;
use App\Filament\Resources\PlacementTests\Schemas\PlacementTestForm;
use App\Filament\Resources\PlacementTests\Schemas\PlacementTestInfolist;
use App\Filament\Resources\PlacementTests\Tables\PlacementTestsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;

class PlacementTestResource extends Resource
{
    use HasHexaLite;

    protected static ?string $model = PlacementTest::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function defineGates(): array
    {
        return [
            'placement_test.index' => __('Allows viewing the placement test list'),
            'placement_test.create' => __('Allows creating a new placement test'),
            'placement_test.update' => __('Allows updating placement tests'),
            'placement_test.delete' => __('Allows deleting placement tests'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return PlacementTestForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PlacementTestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlacementTestsTable::configure($table);
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
            'index' => Pages\ListPlacementTests::route('/'),
            'create' => Pages\CreatePlacementTest::route('/create'),
            'edit' => Pages\EditPlacementTest::route('/{record}/edit'),
            'view' => Pages\ViewPlacementTest::route('/{record}'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('placement_test.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('placement_test.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create placement test.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('placement_test.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('placement_test.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a placement test.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('placement_test.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete placement tests.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}

