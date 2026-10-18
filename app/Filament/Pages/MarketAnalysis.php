<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Marketing\MarketingCluster;
use App\Filament\Resources\CompetitorOffers\CompetitorOfferResource;
use App\Filament\Resources\Competitors\CompetitorResource;
use App\Services\MarketAnalysisDashboardData;
use Filament\Pages\Page;
use Hexters\HexaLite\HasHexaLite;
use UnitEnum;

class MarketAnalysis extends Page
{
    protected static ?int $navigationSort = 1;

    protected static string|UnitEnum|null $navigationGroup = 'Overview';

    protected string $view = 'filament.clusters.marketing.pages.market-analysis';

    protected static ?string $cluster = MarketingCluster::class;

    use HasHexaLite;

    /**
     * @return array<string, mixed>
     */
    public function getDashboardData(): array
    {
        $permissions = [
            'competitor.index' => hexa()->can('competitor.index'),
            'competitor.create' => hexa()->can('competitor.create'),
            'competitor_offer.index' => hexa()->can('competitor_offer.index'),
            'competitor_offer.create' => hexa()->can('competitor_offer.create'),
        ];
        $summary = app(MarketAnalysisDashboardData::class)->summary($permissions);

        $summary['competitors']['index_url'] = $permissions['competitor.index'] ? CompetitorResource::getUrl('index') : null;
        $summary['competitors']['create_url'] = $permissions['competitor.create'] ? CompetitorResource::getUrl('create') : null;
        $summary['offers']['index_url'] = $permissions['competitor_offer.index'] ? CompetitorOfferResource::getUrl('index') : null;
        $summary['offers']['create_url'] = $permissions['competitor_offer.create'] ? CompetitorOfferResource::getUrl('create') : null;

        return $summary;
    }

    public function defineGates(): array
    {
        return [
            'market-analysis.view' => __('Allows viewing the market analysis page'),
        ];
    }

    public static function canAccess(): bool
    {
        return hexa()->can('market-analysis.view');
    }
}
