<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Marketing\MarketingCluster;
use App\Filament\Resources\OpportunityScoringProfiles\OpportunityScoringProfileResource;
use App\Services\OpportunityAnalysis\ProductOpportunityAnalysisService;
use Filament\Pages\Page;
use Hexters\HexaLite\HasHexaLite;
use UnitEnum;

class ProductOpportunityAnalysis extends Page
{
    use HasHexaLite;

    protected static ?int $navigationSort = 2;

    protected static string|UnitEnum|null $navigationGroup = 'Overview';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-light-bulb';

    protected static ?string $navigationLabel = 'Product & Service Opportunities';

    protected static ?string $cluster = MarketingCluster::class;

    protected string $view = 'filament.clusters.marketing.pages.product-opportunity-analysis';

    public ?string $search = null;

    public ?string $productTypeFilter = null;

    public ?string $statusFilter = null;

    /** @return array<string, mixed> */
    public function getAnalysisData(): array
    {
        $permissions = [
            'product.index' => hexa()->can('product.index'),
            'market_trend.index' => hexa()->can('market_trend.index'),
            'competitor_offer.index' => hexa()->can('competitor_offer.index'),
        ];
        $data = app(ProductOpportunityAnalysisService::class)->analyze($permissions, [
            'search' => $this->search,
            'product_type' => $this->productTypeFilter,
            'status' => $this->statusFilter,
        ]);
        $data['can_configure'] = hexa()->can('market_opportunity.configure');
        $data['configuration_url'] = $data['can_configure'] ? OpportunityScoringProfileResource::getUrl('index') : null;

        return $data;
    }

    public function resetFilters(): void
    {
        $this->search = null;
        $this->productTypeFilter = null;
        $this->statusFilter = null;
    }

    public function defineGates(): array
    {
        return ['market_opportunity.view' => __('Allows viewing product and service opportunity analysis')];
    }

    public static function canAccess(): bool
    {
        return hexa()->can('market_opportunity.view');
    }
}
