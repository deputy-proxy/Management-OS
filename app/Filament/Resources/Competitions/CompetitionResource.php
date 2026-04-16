<?php

namespace App\Filament\Resources\Competitions;

use App\Models\Competition;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\Competitions\Pages;
use App\Filament\Resources\Competitions\Schemas\CompetitionForm;
use App\Filament\Resources\Competitions\Schemas\CompetitionInfolist;
use App\Filament\Resources\Competitions\Tables\CompetitionsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use UnitEnum;

class CompetitionResource extends Resource
{
    use HasHexaLite;

    protected static ?string $model = Competition::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static string | UnitEnum | null $navigationGroup = 'Competition Management';

    public function defineGates(): array
    {
        return [
            'competition.index' => __('Allows viewing the competition list'),
            'competition.create' => __('Allows creating a new competition'),
            'competition.update' => __('Allows updating competitions'),
            'competition.delete' => __('Allows deleting competitions'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return CompetitionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CompetitionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CompetitionsTable::configure($table);
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
            'index' => Pages\ListCompetitions::route('/'),
            'create' => Pages\CreateCompetition::route('/create'),
            'edit' => Pages\EditCompetition::route('/{record}/edit'),
            'view' => Pages\ViewCompetition::route('/{record}'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('competition.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('competition.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create competition.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('competition.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('competition.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a competition.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('competition.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete competitions.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
