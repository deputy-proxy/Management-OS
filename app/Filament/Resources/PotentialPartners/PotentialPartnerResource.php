<?php

namespace App\Filament\Resources\PotentialPartners;

use App\Models\PotentialPartner;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\PotentialPartners\Pages;
use App\Filament\Resources\PotentialPartners\Schemas\PotentialPartnerForm;
use App\Filament\Resources\PotentialPartners\Schemas\PotentialPartnerInfolist;
use App\Filament\Resources\PotentialPartners\Tables\PotentialPartnersTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use UnitEnum;
use App\Filament\Clusters\Marketing\MarketingCluster;
class PotentialPartnerResource extends Resource
{
    protected static ?int $navigationSort = 11;
    protected static ?string $cluster = MarketingCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Market Analysis';
    use HasHexaLite;

    protected static ?string $model = PotentialPartner::class;

    protected static ?string $recordTitleAttribute = 'title';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function defineGates(): array
    {
        return [
            ActivitiesRelationManager::class,
            'potential_partner.index' => __('Allows viewing the potential partner list'),
            'potential_partner.create' => __('Allows creating a new potential partner'),
            'potential_partner.update' => __('Allows updating potential partners'),
            'potential_partner.delete' => __('Allows deleting potential partners'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return PotentialPartnerForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PotentialPartnerInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PotentialPartnersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ActivitiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPotentialPartners::route('/'),
            'create' => Pages\CreatePotentialPartner::route('/create'),
            'edit' => Pages\EditPotentialPartner::route('/{record}/edit'),
            'view' => Pages\ViewPotentialPartner::route('/{record}')
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('potential_partner.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('potential_partner.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create potential partner.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('potential_partner.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('potential_partner.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a potential partner.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('potential_partner.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete potential partners.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
