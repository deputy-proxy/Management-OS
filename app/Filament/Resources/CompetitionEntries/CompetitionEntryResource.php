<?php

namespace App\Filament\Resources\CompetitionEntries;

use App\Models\CompetitionEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\CompetitionEntries\Pages;
use App\Filament\Resources\CompetitionEntries\Schemas\CompetitionEntryForm;
use App\Filament\Resources\CompetitionEntries\Schemas\CompetitionEntryInfolist;
use App\Filament\Resources\CompetitionEntries\Tables\CompetitionEntriesTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;

class CompetitionEntryResource extends Resource
{
    use HasHexaLite;

    protected static ?string $model = CompetitionEntry::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function defineGates(): array
    {
        return [
            'competition_entry.index' => __('Allows viewing the competition entry list'),
            'competition_entry.create' => __('Allows creating a new competition entry'),
            'competition_entry.update' => __('Allows updating competition entrys'),
            'competition_entry.delete' => __('Allows deleting competition entrys'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return CompetitionEntryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CompetitionEntryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CompetitionEntriesTable::configure($table);
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
            'index' => Pages\ListCompetitionEntries::route('/'),
            'create' => Pages\CreateCompetitionEntry::route('/create'),
            'edit' => Pages\EditCompetitionEntry::route('/{record}/edit'),
            'view' => Pages\ViewCompetitionEntry::route('/{record}'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('competition_entry.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('competition_entry.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create competition entry.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('competition_entry.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('competition_entry.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a competition entry.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('competition_entry.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete competition entrys.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
