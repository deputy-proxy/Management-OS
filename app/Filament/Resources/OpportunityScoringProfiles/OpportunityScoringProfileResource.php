<?php

namespace App\Filament\Resources\OpportunityScoringProfiles;

use App\Filament\Clusters\Marketing\MarketingCluster;
use App\Filament\Resources\OpportunityScoringProfiles\Schemas\OpportunityScoringProfileForm;
use App\Filament\Resources\OpportunityScoringProfiles\Tables\OpportunityScoringProfilesTable;
use App\Models\OpportunityScoringProfile;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Hexters\HexaLite\HasHexaLite;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class OpportunityScoringProfileResource extends Resource
{
    use HasHexaLite;

    protected static ?int $navigationSort = 15;

    protected static ?string $cluster = MarketingCluster::class;

    protected static string|UnitEnum|null $navigationGroup = 'Market Intelligence Configuration';

    protected static ?string $model = OpportunityScoringProfile::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationLabel = 'Opportunity Scoring Profiles';

    public function defineGates(): array
    {
        return ['market_opportunity.configure' => __('Allows configuring opportunity scoring profiles')];
    }

    public static function form(Schema $schema): Schema
    {
        return OpportunityScoringProfileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OpportunityScoringProfilesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOpportunityScoringProfiles::route('/'),
            'create' => Pages\CreateOpportunityScoringProfile::route('/create'),
            'edit' => Pages\EditOpportunityScoringProfile::route('/{record}/edit'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('market_opportunity.configure') ? Response::allow() : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('market_opportunity.configure') ? Response::allow() : Response::deny();
    }

    /** @param OpportunityScoringProfile $record */
    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('market_opportunity.configure') && $record->status === 'draft' ? Response::allow() : Response::deny();
    }

    /** @param OpportunityScoringProfile $record */
    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('market_opportunity.configure') && $record->status === 'draft' ? Response::allow() : Response::deny();
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('market_opportunity.configure') ? Response::allow() : Response::deny();
    }
}
