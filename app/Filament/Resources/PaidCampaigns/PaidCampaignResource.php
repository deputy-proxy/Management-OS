<?php

namespace App\Filament\Resources\PaidCampaigns;

use App\Models\PaidCampaign;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\PaidCampaigns\Pages;
use App\Filament\Resources\PaidCampaigns\Schemas\PaidCampaignForm;
use App\Filament\Resources\PaidCampaigns\Schemas\PaidCampaignInfolist;
use App\Filament\Resources\PaidCampaigns\Tables\PaidCampaignsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use UnitEnum;
use App\Filament\Clusters\Marketing\MarketingCluster;

class PaidCampaignResource extends Resource
{
    protected static ?int $navigationSort = 4;
    protected static ?string $cluster = MarketingCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Content Management';
    use HasHexaLite;

    protected static ?string $model = PaidCampaign::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function defineGates(): array
    {
        return [
            'paid_campaign.index' => __('Allows viewing the paid campaign list'),
            'paid_campaign.create' => __('Allows creating a new paid campaign'),
            'paid_campaign.update' => __('Allows updating paid campaigns'),
            'paid_campaign.delete' => __('Allows deleting paid campaigns'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return PaidCampaignForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PaidCampaignInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaidCampaignsTable::configure($table);
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
            'index' => Pages\ListPaidCampaigns::route('/'),
            'create' => Pages\CreatePaidCampaign::route('/create'),
            'edit' => Pages\EditPaidCampaign::route('/{record}/edit'),
            'view' => Pages\ViewPaidCampaign::route('/{record}'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('paid_campaign.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('paid_campaign.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create paid campaign.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('paid_campaign.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('paid_campaign.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a paid campaign.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('paid_campaign.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete paid campaigns.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
