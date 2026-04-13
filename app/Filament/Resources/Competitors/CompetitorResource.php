<?php

namespace App\Filament\Resources\Competitors;

use App\Models\Competitor;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\Competitors\Pages;
use App\Filament\Resources\Competitors\Schemas\CompetitorForm;
use App\Filament\Resources\Competitors\Schemas\CompetitorInfolist;
use App\Filament\Resources\Competitors\Tables\CompetitorsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;

class CompetitorResource extends Resource
{
    use HasHexaLite;

    protected static ?string $model = Competitor::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function defineGates(): array
    {
        return [
            'competitor.index' => __('Allows viewing the competitor list'),
            'competitor.create' => __('Allows creating a new competitor'),
            'competitor.update' => __('Allows updating competitors'),
            'competitor.delete' => __('Allows deleting competitors'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return CompetitorForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CompetitorInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CompetitorsTable::configure($table);
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
            'index' => Pages\ListCompetitors::route('/'),
            'create' => Pages\CreateCompetitor::route('/create'),
            'edit' => Pages\EditCompetitor::route('/{record}/edit'),
            'view' => Pages\ViewCompetitor::route('/{record}'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('competitor.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('competitor.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create competitor.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('competitor.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('competitor.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a competitor.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('competitor.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete competitors.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
