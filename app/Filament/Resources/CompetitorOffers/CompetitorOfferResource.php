<?php

namespace App\Filament\Resources\CompetitorOffers;

use App\Models\CompetitorOffer;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\CompetitorOffers\Pages;
use App\Filament\Resources\CompetitorOffers\Schemas\CompetitorOfferForm;
use App\Filament\Resources\CompetitorOffers\Schemas\CompetitorOfferInfolist;
use App\Filament\Resources\CompetitorOffers\Tables\CompetitorOffersTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use UnitEnum;
use App\Filament\Clusters\Marketing\MarketingCluster;

class CompetitorOfferResource extends Resource
{
    protected static ?int $navigationSort = 9;
    protected static ?string $cluster = MarketingCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Market Analysis';
    use HasHexaLite;

    protected static ?string $model = CompetitorOffer::class;

    protected static ?string $recordTitleAttribute = 'title';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function defineGates(): array
    {
        return [
            'competitor_offer.index' => __('Allows viewing the competitor offer list'),
            'competitor_offer.create' => __('Allows creating a new competitor offer'),
            'competitor_offer.update' => __('Allows updating competitor offers'),
            'competitor_offer.delete' => __('Allows deleting competitor offers'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return CompetitorOfferForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CompetitorOfferInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CompetitorOffersTable::configure($table);
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
            'index' => Pages\ListCompetitorOffers::route('/'),
            'create' => Pages\CreateCompetitorOffer::route('/create'),
            'edit' => Pages\EditCompetitorOffer::route('/{record}/edit'),
            'view' => Pages\ViewCompetitorOffer::route('/{record}'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('competitor_offer.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('competitor_offer.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create competitor offer.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('competitor_offer.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('competitor_offer.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a competitor offer.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('competitor_offer.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete competitor offers.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
