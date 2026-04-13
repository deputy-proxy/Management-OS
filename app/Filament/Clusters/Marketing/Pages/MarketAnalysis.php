<?php

namespace App\Filament\Clusters\Marketing\Pages;

use App\Filament\Clusters\Marketing\MarketingCluster;
use Filament\Pages\Page;

class MarketAnalysis extends Page
{
    protected string $view = 'filament.clusters.marketing.pages.market-analysis';

    protected static ?string $cluster = MarketingCluster::class;
}
