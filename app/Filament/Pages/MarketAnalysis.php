<?php

namespace App\Filament\Clusters\Marketing\Pages;

use App\Filament\Clusters\Marketing\MarketingCluster;
use Filament\Pages\Page;
use UnitEnum;

class MarketAnalysis extends Page
{
    protected static ?int $navigationSort = 11;
    protected static string|UnitEnum|null $navigationGroup = 'Market Analysis';
    protected string $view = 'filament.clusters.marketing.pages.market-analysis';
    protected static ?string $cluster = MarketingCluster::class;
}
