<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Marketing\MarketingCluster;
use App\Filament\Resources\CompetitorOffers\CompetitorOfferResource;
use App\Filament\Resources\Competitors\CompetitorResource;
use App\Filament\Resources\MarketTrends\MarketTrendResource;
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

    public ?string $periodStart = null;

    public ?string $periodEnd = null;

    public ?string $marketFilter = null;

    public ?string $segmentFilter = null;

    public ?string $metricFilter = null;

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
            'market_trend.index' => hexa()->can('market_trend.index'),
            'google_ads_metrics.view' => hexa()->can('paid_campaign.index'),
            'paid_campaign.index' => hexa()->can('paid_campaign.index'),
        ];
        $summary = app(MarketAnalysisDashboardData::class)->summary($permissions, filters: [
            'period_start' => $this->periodStart,
            'period_end' => $this->periodEnd,
            'market' => $this->marketFilter,
            'segment' => $this->segmentFilter,
            'metric' => $this->metricFilter,
        ]);

        $summary['competitors']['index_url'] = $permissions['competitor.index'] ? CompetitorResource::getUrl('index') : null;
        $summary['competitors']['create_url'] = $permissions['competitor.create'] ? CompetitorResource::getUrl('create') : null;
        $summary['offers']['index_url'] = $permissions['competitor_offer.index'] ? CompetitorOfferResource::getUrl('index') : null;
        $summary['offers']['create_url'] = $permissions['competitor_offer.create'] ? CompetitorOfferResource::getUrl('create') : null;
        foreach ($summary['market_trends']['recent'] as &$trend) {
            $trend['detail_url'] = $permissions['market_trend.index']
                ? MarketTrendResource::getUrl('view', ['record' => $trend['id']])
                : null;
        }
        unset($trend);
        foreach ($summary['offers']['recent_observations'] as &$observation) {
            $observation['history_url'] = $permissions['competitor_offer.index']
                ? CompetitorOfferResource::getUrl('view', ['record' => $observation['offer_id']])
                : null;
        }
        unset($observation);

        return $summary;
    }

    public function resetFilters(): void
    {
        $this->periodStart = null;
        $this->periodEnd = null;
        $this->marketFilter = null;
        $this->segmentFilter = null;
        $this->metricFilter = null;
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
